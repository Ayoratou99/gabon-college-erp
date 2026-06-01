<?php

declare(strict_types=1);

namespace Modules\Concours\Http\Controllers\Public;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Modules\Concours\Models\Candidat;
use Modules\Concours\Models\ConcoursSession;
use Modules\Concours\Models\ResultPublication;
use Modules\Concours\Services\CandidatPdfService;
use Modules\Concours\Services\PlanningService;
use Modules\Concours\Services\PublicCandidatLookupService;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 *   GET  /candidat/{matricule}                 → personal dashboard
 *   GET  /resultats                            → latest published results
 *
 * The matricule is opaque (16 chars) so it functions as a bearer token.
 * Full auth lives in the candidat User account created at publication.
 */
final class CandidatDashboardController extends Controller
{
    public function __construct(
        private readonly PublicCandidatLookupService $lookup,
        private readonly PlanningService $planning,
        private readonly CandidatPdfService $pdfs,
    ) {}

    /**
     * Show the identity-gate form before the actual PDF download. The
     * candidat enters their email + telephone + reCAPTCHA so we don't
     * leak the matricule URL into search engines / shared links.
     *
     *   GET /candidat/{matricule}/pdf/{document}
     */
    public function showPdfGate(string $matricule, string $document): View|RedirectResponse
    {
        if (! in_array($document, ['fiche', 'emploi-du-temps'], true)) {
            abort(Response::HTTP_NOT_FOUND);
        }

        $candidat = $this->lookup->byMatricule($matricule);
        if ($candidat === null) {
            return redirect()->route('concours.public.status.form')
                ->withErrors(['matricule' => 'Matricule inconnu.']);
        }

        $eligible = match ($document) {
            'fiche'           => $candidat->statut !== Candidat::STATUS_REJETE,
            'emploi-du-temps' => in_array($candidat->statut,
                [Candidat::STATUS_VALID, Candidat::STATUS_ADMIS], true),
            default           => false,
        };
        if (! $eligible) {
            return redirect()->route('concours.public.candidat.dashboard', $matricule)
                ->withErrors(['statut' => 'Ce document n\'est pas encore disponible pour votre dossier.']);
        }

        return view('concours::public.payment.pdf-gate', [
            'candidat' => $candidat,
            'document' => $document,
        ]);
    }

    /**
     * Verify the candidat's email + telephone match the dossier (reCAPTCHA
     * checked by middleware), then stream the requested PDF. Mismatches
     * return the same generic message — attackers can't probe which field
     * was wrong.
     *
     *   POST /candidat/{matricule}/pdf/{document}
     */
    public function streamPdf(Request $request, string $matricule, string $document): mixed
    {
        if (! in_array($document, ['fiche', 'emploi-du-temps'], true)) {
            abort(Response::HTTP_NOT_FOUND);
        }

        $data = $request->validate([
            'email'     => ['required', 'email:rfc'],
            'telephone' => ['required', 'string', 'max:30'],
        ]);

        $candidat = $this->lookup->byMatricule($matricule);
        if ($candidat === null) {
            return redirect()->route('concours.public.status.form')
                ->withErrors(['matricule' => 'Matricule inconnu.']);
        }

        $emailOk = hash_equals(
            mb_strtolower((string) $candidat->email),
            mb_strtolower(trim($data['email'])),
        );
        $telOk = hash_equals(
            preg_replace('/\s+/', '', (string) $candidat->telephone) ?? '',
            preg_replace('/\s+/', '', trim($data['telephone'])) ?? '',
        );

        if (! $emailOk || ! $telOk) {
            return back()->withInput()->withErrors([
                'email' => 'Informations incorrectes — vérifiez l\'email et le téléphone fournis lors de l\'inscription.',
            ]);
        }

        return match ($document) {
            'fiche'           => $this->pdfs->ficheCandidat($candidat),
            'emploi-du-temps' => $this->pdfs->emploiDuTemps($candidat),
            default           => abort(Response::HTTP_NOT_FOUND),
        };
    }

    public function dashboard(string $matricule): View|RedirectResponse
    {
        $candidat = $this->lookup->byMatricule($matricule);
        if ($candidat === null) {
            return redirect()->route('concours.public.status.form')
                ->withErrors(['matricule' => 'Matricule inconnu.']);
        }

        $candidat->load([
            'centre', 'session', 'premierChoix.cycle', 'sectionOrientation', 'payments',
            'motifsRejet',
            'documents.documentRequis:id,code,libelle',
        ]);

        $schedule = match ($candidat->statut) {
            Candidat::STATUS_VALID, Candidat::STATUS_ADMIS => $this->planning->planningForCandidat($candidat),
            default                                          => collect(),
        };

        $publication = $candidat->statut === Candidat::STATUS_ADMIS
            ? ResultPublication::latestActiveFor($candidat->concours_session_id)
            : null;

        return view('concours::public.dashboard', [
            'candidat'    => $candidat,
            'schedule'    => $schedule,
            'publication' => $publication,
        ]);
    }

    public function results(Request $request): View
    {
        // Sessions list: anything that has an active publication, ordered newest first.
        $sessions = ConcoursSession::query()
            ->whereIn('id', ResultPublication::query()
                ->where('active', true)
                ->select('concours_session_id'))
            ->with('anneeAcademique:id,code')
            ->orderByDesc('date_concours')
            ->get(['id', 'code', 'libelle', 'date_concours', 'annee_academique_id']);

        // Selected session: ?session=CODE overrides; else the public-current
        // (latest) session if it has results, else the most recently published
        // session (so old years stay readable). NOT the back-office est_active
        // pointer — the public results must not follow what an admin is viewing.
        $active = ConcoursSession::publicCurrent();
        $code   = (string) $request->query('session', '');

        $session = match (true) {
            $code !== ''  => $sessions->firstWhere('code', $code) ?? ($active?->code === $code ? $active : null),
            $active && $sessions->contains('id', $active->id) => $active,
            default       => $sessions->first(),
        };

        $publication = $session ? ResultPublication::latestActiveFor($session->id) : null;

        $admis = $publication ? Candidat::query()
            ->where('concours_session_id', $session->id)
            ->where('statut', Candidat::STATUS_ADMIS)
            ->with(['sectionOrientation:id,nom,code'])
            ->orderBy('section_orientation_id')
            ->orderByDesc('moyenne')
            ->get(['id', 'nom', 'prenom', 'matricule_public', 'moyenne', 'rang', 'section_orientation_id'])
            : collect();

        return view('concours::public.results', [
            'session'     => $session,
            'sessions'    => $sessions,
            'publication' => $publication,
            'admis'       => $admis,
        ]);
    }

    /**
     * Download the officially published PDF for a given session, if one was
     * uploaded at publication time. 404 otherwise — we never expose other
     * files in the same disk.
     */
    public function resultsDownload(string $sessionCode): BinaryFileResponse|StreamedResponse|RedirectResponse
    {
        $session = ConcoursSession::query()->where('code', $sessionCode)->first();
        if ($session === null) {
            abort(Response::HTTP_NOT_FOUND);
        }

        $publication = ResultPublication::latestActiveFor($session->id);
        if ($publication === null || ! $publication->fichier_path) {
            abort(Response::HTTP_NOT_FOUND);
        }

        $disk = $publication->fichier_disk ?: 'local';
        $path = $publication->fichier_path;

        $storage = Storage::disk($disk);
        if (! $storage->exists($path)) {
            abort(Response::HTTP_NOT_FOUND);
        }

        $filename = "resultats-{$session->code}.pdf";
        return $storage->download($path, $filename, [
            'Content-Type' => 'application/pdf',
        ]);
    }
}
