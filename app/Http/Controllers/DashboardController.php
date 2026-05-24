<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Foundation\Permissions\ScopedQuery;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Concours\Models\Candidat;
use Modules\Concours\Models\ConcoursSession;
use Modules\Concours\Models\Payment;

/**
 * Single dashboard for every back-office role.
 *
 * Counts are scope-filtered through the RBAC ScopedQuery so the cards
 * the chef-centre sees reflect their centre only — DG/DE see everything.
 *
 * Kept intentionally read-only and dependency-light; richer reporting
 * lands in the Reporting module (item 6).
 */
final class DashboardController extends Controller
{
    public function __construct(
        private readonly ScopedQuery $scoped,
    ) {}

    public function __invoke(Request $request): View
    {
        $session = ConcoursSession::active();
        $user = $request->user();

        $base = Candidat::query();
        if ($session !== null) {
            $base->where('concours_session_id', $session->id);
        }

        $scoped = (clone $base)->getQuery();
        $scoped = $this->scoped->apply(Candidat::query(), $user, 'view', 'candidats');
        if ($session !== null) {
            $scoped->where('concours_session_id', $session->id);
        }

        $byStatus = (clone $scoped)
            ->selectRaw('statut, COUNT(*) AS n')
            ->groupBy('statut')
            ->pluck('n', 'statut');

        $kpis = [
            'total'   => (clone $scoped)->count(),
            'en_cours'=> (int) ($byStatus['non']    ?? 0),
            'oui'     => (int) ($byStatus['oui']    ?? 0),
            'valid'   => (int) ($byStatus['valid']  ?? 0),
            'rejete'  => (int) ($byStatus['rejete'] ?? 0),
            'admis'   => (int) ($byStatus['admis']  ?? 0),
            'paid_amount' => (int) Payment::query()
                ->when($session, fn ($q) => $q->where('concours_session_id', $session->id))
                ->where('status', Payment::STATUS_PAID)
                ->sum('amount'),
        ];

        $recent = (clone $scoped)
            ->latest('created_at')
            ->with(['centre:id,nom', 'premierChoix:id,nom'])
            ->limit(8)
            ->get(['id', 'matricule_public', 'nom', 'prenom', 'centre_id', 'section_premier_choix_id', 'statut', 'created_at']);

        return view('admin.dashboard', [
            'session' => $session,
            'kpis'    => $kpis,
            'recent'  => $recent,
        ]);
    }
}
