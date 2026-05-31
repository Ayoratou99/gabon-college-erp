<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Foundation\Permissions\ScopedQuery;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Modules\Concours\Models\Candidat;
use Modules\Concours\Models\ConcoursSession;
use Modules\Concours\Models\Payment;

/**
 * Back-office home — a real KPI dashboard scoped through RBAC.
 *
 * Counts (and chart data) all flow through `ScopedQuery`, so the same
 * page works for the DG (all centres) and a chef-centre (their centre
 * only) without per-role branching here.
 */
final class DashboardController extends Controller
{
    public function __construct(
        private readonly ScopedQuery $scoped,
    ) {}

    public function __invoke(Request $request): View
    {
        // Session resolution: explicit ?session=CODE wins, else the active one.
        // This lets admins inspect historical sessions without having to flip
        // the global active flag.
        $code        = trim((string) $request->query('session', ''));
        $allSessions = ConcoursSession::query()
            ->with('anneeAcademique:id,code')
            ->withCount('candidats')
            ->orderByDesc('est_active')
            ->orderByDesc('date_concours')
            ->get(['id', 'code', 'libelle', 'date_concours', 'annee_academique_id', 'est_active']);
        $session = $code !== ''
            ? ($allSessions->firstWhere('code', $code) ?? ConcoursSession::active())
            : ConcoursSession::active();
        // Final fallback for fresh installs: the most recent session by date.
        $session ??= $allSessions->first();

        $user = $request->user();

        $base = $this->scoped->apply(Candidat::query(), $user, 'view', 'candidats');
        if ($session !== null) {
            $base->where('concours_session_id', $session->id);
        }

        $byStatus = (clone $base)
            ->selectRaw('statut, COUNT(*) AS n')
            ->groupBy('statut')
            ->pluck('n', 'statut');

        $kpis = [
            'total'       => (clone $base)->count(),
            'en_cours'    => (int) ($byStatus['non']    ?? 0),
            'oui'         => (int) ($byStatus['oui']    ?? 0),
            'valid'       => (int) ($byStatus['valid']  ?? 0),
            'rejete'      => (int) ($byStatus['rejete'] ?? 0),
            'admis'       => (int) ($byStatus['admis']  ?? 0),
            'paid_amount' => (int) Payment::query()
                ->when($session, fn ($q) => $q->where('concours_session_id', $session->id))
                ->where('status', Payment::STATUS_PAID)
                ->sum('amount'),
            'paid_count'  => (int) Payment::query()
                ->when($session, fn ($q) => $q->where('concours_session_id', $session->id))
                ->where('status', Payment::STATUS_PAID)
                ->count(),
        ];

        // Conversion ratios — handy single numbers for the top band.
        $kpis['acceptance_rate']  = $kpis['total'] === 0
            ? 0
            : (int) round(100 * ($kpis['oui'] + $kpis['valid'] + $kpis['admis']) / $kpis['total']);
        $kpis['payment_rate']     = ($kpis['oui'] + $kpis['valid']) === 0
            ? 0
            : (int) round(100 * $kpis['valid'] / ($kpis['oui'] + $kpis['valid']));

        // 14-day registration timeline (Chart.js).
        $today = CarbonImmutable::today();
        $from  = $today->subDays(13);

        $rawDaily = (clone $base)
            ->where('created_at', '>=', $from)
            ->selectRaw('DATE(created_at) AS d, COUNT(*) AS n')
            ->groupBy('d')->orderBy('d')
            ->pluck('n', 'd');

        $timeline = [];
        for ($i = 0; $i < 14; $i++) {
            $day = $from->addDays($i)->toDateString();
            $timeline[] = [
                'label' => $from->addDays($i)->format('d/m'),
                'value' => (int) ($rawDaily[$day] ?? 0),
            ];
        }

        // Top centres by candidat count (capped to 5).
        $topCentres = (clone $base)
            ->join('centres', 'centres.id', '=', 'candidats.centre_id')
            ->selectRaw('centres.nom AS centre, COUNT(*) AS n,
                SUM(CASE WHEN candidats.statut = ? THEN 1 ELSE 0 END) AS valides',
                [Candidat::STATUS_VALID])
            ->groupBy('centres.nom')
            ->orderByDesc('n')
            ->limit(5)
            ->get();

        $recent = (clone $base)
            ->latest('created_at')
            ->with(['centre:id,nom', 'premierChoix:id,nom'])
            ->limit(8)
            ->get(['id', 'matricule_public', 'nom', 'prenom', 'centre_id',
                   'section_premier_choix_id', 'statut', 'created_at']);

        // ------- "Répartition par centre × statut" (stacked bar) -------
        $rawCentreStatus = (clone $base)
            ->join('centres', 'centres.id', '=', 'candidats.centre_id')
            ->selectRaw('centres.nom AS centre, candidats.statut, COUNT(*) AS n')
            ->groupBy('centres.nom', 'candidats.statut')
            ->orderBy('centres.nom')
            ->get();

        $centreNames = $rawCentreStatus->pluck('centre')->unique()->values()->all();
        $statusOrder = ['non', 'oui', 'valid', 'rejete', 'admis'];
        $centreXStatus = [];
        foreach ($statusOrder as $st) {
            $centreXStatus[$st] = array_map(
                static fn ($name): int => (int) ($rawCentreStatus
                    ->firstWhere(fn ($r) => $r->centre === $name && $r->statut === $st)?->n ?? 0),
                $centreNames,
            );
        }

        return view('admin.dashboard', [
            'session'       => $session,
            'allSessions'   => $allSessions,
            'kpis'          => $kpis,
            'byStatus'      => $byStatus,
            'timeline'      => $timeline,
            'topCentres'    => $topCentres,
            'centreNames'   => $centreNames,
            'centreXStatus' => $centreXStatus,
            'recent'        => $recent,
        ]);
    }
}
