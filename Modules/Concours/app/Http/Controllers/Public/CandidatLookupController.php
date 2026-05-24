<?php

declare(strict_types=1);

namespace Modules\Concours\Http\Controllers\Public;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Modules\AcademicStructure\Models\Section;
use Modules\Concours\Http\Requests\LookupCandidatRequest;
use Modules\Concours\Http\Requests\ModifyCandidatRequest;
use Modules\Concours\Models\Candidat;
use Modules\Concours\Models\ConcoursSession;
use Modules\Concours\Services\CandidatModificationService;
use Modules\Concours\Services\PublicCandidatLookupService;
use Modules\Referentiels\Models\DocumentRequis;
use Modules\Referentiels\Models\Nationalite;
use Modules\Referentiels\Models\SerieBac;
use Modules\UserManagement\Exceptions\LoginThrottledException;

/**
 * Public lookup + modification flow.
 *
 *   /verifier-demande               GET/POST  status check by matricule
 *   /recuperer-dossier              GET/POST  identify by email+phone
 *                                              → stashes a session token, redirects
 *   /modifier-dossier/{token}       GET       renders the edit form (token gated)
 *   /modifier-dossier/{token}       POST      applies changes via CandidatModificationService
 *   /modifier-dossier-succes/{m}    GET       confirmation
 *
 * Token storage:
 *   session(modification_token)    — random ULID, must match URL token
 *   session(modification_candidat) — UUID of the candidat being edited
 *   session(modification_expires)  — unix ts, 1 h TTL by default
 *
 * The candidat ID is read from SESSION, never from the URL — a forged
 * URL with a valid-looking token still can't address a different dossier.
 */
final class CandidatLookupController extends Controller
{
    public function __construct(
        private readonly PublicCandidatLookupService $lookup,
        private readonly CandidatModificationService $modifications,
    ) {}

    // ---------------------------------------------------- Status check

    public function showStatusForm(): View
    {
        return view('concours::public.lookup.status-form');
    }

    public function status(Request $request): View
    {
        $matricule = (string) $request->string('matricule')->toString();
        $candidat = $this->lookup->byMatricule($matricule);

        return view('concours::public.lookup.status-result', [
            'matricule' => $matricule,
            'candidat'  => $candidat,
        ]);
    }

    // ---------------------------------------------------- Recovery (gate)

    public function showModifyForm(): View
    {
        return view('concours::public.lookup.modify-form');
    }

    public function submitLookup(LookupCandidatRequest $request): RedirectResponse|View
    {
        try {
            $candidat = $this->lookup->byEmailAndPhone(
                email: (string) $request->validated('email'),
                telephone: (string) $request->validated('telephone'),
                ipAddress: (string) $request->ip(),
            );
        } catch (LoginThrottledException $e) {
            return back()->withInput()->withErrors(['email' => $e->getMessage()]);
        }

        // Same error message for "not found" AND "not rejected" so we don't
        // leak whether a given email/phone exists in the DB.
        if ($candidat === null || $candidat->statut !== Candidat::STATUS_REJETE) {
            return back()->withInput()->withErrors([
                'email' => 'Aucun dossier rejeté n\'a été trouvé avec ces informations.',
            ]);
        }

        $token = (string) Str::ulid();
        $keys  = (array) config('concours.public_lookup.session_keys');

        $request->session()->put($keys['modification_token'],    $token);
        $request->session()->put($keys['modification_candidat'], $candidat->getKey());
        $request->session()->put(
            $keys['modification_expires'],
            now()->addSeconds((int) config('concours.public_lookup.modification_token_ttl', 3600))->timestamp,
        );

        return redirect()->route('concours.public.modify.form', ['token' => $token]);
    }

    // ---------------------------------------------------- Edit form

    public function showEditForm(Request $request, string $token): View|RedirectResponse
    {
        $candidat = $this->resolveTokenized($request, $token);
        if (! $candidat instanceof Candidat) {
            return $candidat;
        }

        $candidat->load(['motifsRejet', 'documents.documentRequis']);

        return view('concours::public.lookup.edit', [
            'token'         => $token,
            'candidat'      => $candidat,
            'session'       => ConcoursSession::query()->find($candidat->concours_session_id),
            'centres'       => $this->visibleCentres($candidat),
            'sections'      => Section::query()->where('ouvert_au_concours', true)->where('active', true)->orderBy('nom')->get(),
            'nationalites'  => Nationalite::query()->where('active', true)->orderBy('display_order')->orderBy('nom')->get(),
            'series'        => SerieBac::query()->where('active', true)->ordered()->get(),
            'documents'     => DocumentRequis::query()->where('active', true)->ordered()->get(),
        ]);
    }

    public function submitEdit(ModifyCandidatRequest $request, string $token): RedirectResponse
    {
        // Authorization already covered by ModifyCandidatRequest::authorize().
        $candidat = $request->candidat();

        $this->modifications->apply($request->toDto());

        // One-shot token: invalidate as soon as we accept the submission.
        $keys = (array) config('concours.public_lookup.session_keys');
        $request->session()->forget([
            $keys['modification_token'],
            $keys['modification_candidat'],
            $keys['modification_expires'],
        ]);

        return redirect()->route('concours.public.modify.success', $candidat->matricule_public);
    }

    public function editSuccess(string $matricule): View
    {
        return view('concours::public.lookup.edit-success', ['matricule' => $matricule]);
    }

    // ---------------------------------------------------- helpers

    private function resolveTokenized(Request $request, string $token): Candidat|RedirectResponse
    {
        $keys      = (array) config('concours.public_lookup.session_keys');
        $stored    = $request->session()->get($keys['modification_token']);
        $candidatId = $request->session()->get($keys['modification_candidat']);
        $expires   = $request->session()->get($keys['modification_expires']);

        $expired = ! is_int($expires) || $expires < time();
        if (! is_string($stored) || $stored !== $token || $candidatId === null || $expired) {
            return redirect()->route('concours.public.lookup.form')
                ->withErrors(['email' => 'Votre session de modification a expiré. Veuillez recommencer.']);
        }

        $candidat = Candidat::query()->find($candidatId);
        if ($candidat === null) {
            return redirect()->route('concours.public.lookup.form');
        }
        return $candidat;
    }

    /** @return \Illuminate\Support\Collection<int, \Modules\Concours\Models\Centre> */
    private function visibleCentres(Candidat $candidat): \Illuminate\Support\Collection
    {
        $session = ConcoursSession::query()->find($candidat->concours_session_id);
        if ($session === null) {
            return collect();
        }
        return $session->centres()->wherePivot('active', true)->orderBy('nom')->get();
    }
}
