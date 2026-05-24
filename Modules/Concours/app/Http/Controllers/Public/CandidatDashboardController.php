<?php

declare(strict_types=1);

namespace Modules\Concours\Http\Controllers\Public;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Modules\Concours\Models\Candidat;
use Modules\Concours\Models\ResultPublication;
use Modules\Concours\Services\PlanningService;
use Modules\Concours\Services\PublicCandidatLookupService;

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
    ) {}

    public function dashboard(string $matricule): View|RedirectResponse
    {
        $candidat = $this->lookup->byMatricule($matricule);
        if ($candidat === null) {
            return redirect()->route('concours.public.status.form')
                ->withErrors(['matricule' => 'Matricule inconnu.']);
        }

        $candidat->load(['centre', 'session', 'premierChoix.cycle', 'sectionOrientation', 'payments']);

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

    public function results(): View
    {
        $session = \Modules\Concours\Models\ConcoursSession::active();
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
            'publication' => $publication,
            'admis'       => $admis,
        ]);
    }
}
