<?php

declare(strict_types=1);

namespace Modules\Concours\Http\Controllers\Admin\Pages;

use App\Foundation\Http\DataTables\DataTablesQuery;
use App\Foundation\Permissions\PermissionChecker;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Concours\Models\Centre;
use Modules\Concours\Models\ConcoursSession;
use Modules\Concours\Models\Payment;
use Symfony\Component\HttpFoundation\Response;

/**
 * Admin Payments index — strictly **read-only**.
 *
 * Reachable by DG, DE, and super-admin (the `view:payments:*` permission
 * was intentionally NOT granted to chef-centre; centre staff manage their
 * candidats' dossiers but financial visibility lives one level above).
 *
 * Two endpoints:
 *
 *   GET  /admin/concours/payments              → index (form + table shell)
 *   POST /admin/concours/payments/data         → DataTables server-side feed
 *   GET  /admin/concours/payments/{payment}    → detail card (candidat + history)
 *
 * No edit / no refund / no cancel — those would route through eBilling
 * and we don't have admin tooling for that yet. The page exists so DG/DE
 * can answer "did candidate X pay?" without fishing through pgAdmin.
 */
final class PaymentPageController extends Controller
{
    public function __construct(
        private readonly PermissionChecker $checker,
    ) {}

    public function index(Request $request): View
    {
        if (! $this->checker->can($request->user(), 'view:payments:*')) {
            abort(Response::HTTP_FORBIDDEN);
        }

        return view('concours::admin.payments.index', [
            'centres'  => Centre::query()->where('active', true)->orderBy('nom')->get(['id', 'nom']),
            'sessions' => ConcoursSession::query()
                ->orderByDesc('date_concours')
                ->get(['id', 'code', 'libelle']),
            'statuses' => [
                Payment::STATUS_INIT    => 'Initié',
                Payment::STATUS_PENDING => 'En attente',
                Payment::STATUS_PAID    => 'Payé',
                Payment::STATUS_FAILED  => 'Échoué',
            ],
        ]);
    }

    /**
     * Server-side DataTables feed. Filters arrive under `filters.*`:
     *   status, centre_id, concours_session_id, paid_only, date_from, date_to.
     */
    public function data(Request $request): JsonResponse
    {
        if (! $this->checker->can($request->user(), 'view:payments:*')) {
            abort(Response::HTTP_FORBIDDEN);
        }

        $base = Payment::query()
            ->with([
                'candidat:id,matricule_public,nom,prenom,email,telephone,centre_id,concours_session_id',
                'candidat.centre:id,nom',
                'session:id,code,libelle',
            ]);

        // Hide the QA test candidate's payment(s) from everyone but super-admin.
        // whereDoesntHave (not whereHas) so payments with a null/deleted
        // candidat are still listed.
        if (! ($request->user()?->hasRole('super-admin') ?? false)) {
            $base->whereDoesntHave('candidat', fn (Builder $q) => $q->where('is_test', true));
        }

        $showUrl = fn (string $id) => route('admin.pages.concours.payments.show', $id);

        return DataTablesQuery::for($base)
            ->searchable([
                // Searching across joined columns would force a join — keep
                // the search local to the payment row + the candidat fields
                // we already eager-load. Whatever DT search misses can be
                // narrowed with the matricule/email filters above.
                'external_reference', 'ebilling_id', 'status',
            ])
            ->orderable([
                'created_at'         => 'created_at',
                'paid_at'            => 'paid_at',
                'amount'             => 'amount',
                'status'             => 'status',
                'external_reference' => 'external_reference',
            ])
            ->filterUsing(fn (Builder $q, array $filters) => $this->applyFilters($q, $filters))
            ->transform(fn (Payment $p): array => [
                'id'                 => $p->id,
                'created_at'         => $p->created_at?->format('d/m/Y H:i'),
                'matricule'          => $p->candidat
                    ? sprintf('<code>%s</code>', e($p->candidat->matricule_public))
                    : '<span class="text-muted">—</span>',
                'candidat'           => $p->candidat
                    ? e($p->candidat->nom) . ' ' . e($p->candidat->prenom)
                    : '<span class="text-muted">—</span>',
                'centre'             => e($p->candidat?->centre?->nom ?? '—'),
                'session'            => e($p->session?->code ?? '—'),
                'amount'             => number_format((int) $p->amount, 0, ',', ' ') . '&nbsp;' . e($p->currency),
                'status'             => sprintf(
                    '<span class="badge bg-%s">%s</span>',
                    self::statusColor($p->status),
                    e($p->status),
                ),
                'paid_at'            => $p->paid_at?->format('d/m/Y H:i') ?? '<span class="text-muted">—</span>',
                'external_reference' => sprintf(
                    '<code class="small text-truncate d-inline-block" style="max-width:14ch;" title="%s">%s</code>',
                    e($p->external_reference ?? ''),
                    e(mb_substr((string) $p->external_reference, 0, 14)),
                ),
                'actions'            => sprintf(
                    '<a href="%s" class="btn btn-sm btn-outline-primary">Détail</a>',
                    e($showUrl($p->id)),
                ),
            ])
            ->respond($request);
    }

    /**
     * Detail view — read-only, with the linked candidat dossier + payload.
     */
    public function show(Request $request, string $payment): View
    {
        if (! $this->checker->can($request->user(), 'view:payments:*')) {
            abort(Response::HTTP_FORBIDDEN);
        }

        /** @var Payment|null $row */
        $row = Payment::query()
            ->with([
                'candidat:id,matricule_public,nom,prenom,email,telephone,centre_id,concours_session_id,statut,is_test',
                'candidat.centre:id,nom',
                'candidat.session:id,code,libelle',
                'session:id,code,libelle',
            ])
            ->find($payment);

        if ($row === null) {
            abort(Response::HTTP_NOT_FOUND);
        }

        // A test-candidate payment is visible to super-admin only.
        if (($row->candidat?->is_test ?? false)
            && ! ($request->user()?->hasRole('super-admin') ?? false)
        ) {
            abort(Response::HTTP_NOT_FOUND);
        }

        return view('concours::admin.payments.show', [
            'payment'     => $row,
            'statusColor' => self::statusColor($row->status),
        ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function applyFilters(Builder $q, array $filters): void
    {
        if (! empty($filters['status'])) {
            $q->where('status', $filters['status']);
        }
        if (! empty($filters['concours_session_id'])) {
            $q->where('concours_session_id', $filters['concours_session_id']);
        }
        if (! empty($filters['centre_id'])) {
            // Join through the candidat to filter on centre.
            $q->whereHas('candidat', fn (Builder $sub) => $sub->where('centre_id', $filters['centre_id']));
        }
        if (! empty($filters['paid_only'])) {
            $q->where('status', Payment::STATUS_PAID);
        }
        if (! empty($filters['date_from'])) {
            $q->whereDate('created_at', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $q->whereDate('created_at', '<=', $filters['date_to']);
        }
        if (! empty($filters['matricule'])) {
            $needle = (string) $filters['matricule'];
            $q->whereHas('candidat', fn (Builder $sub) => $sub
                ->where('matricule_public', 'ilike', "%{$needle}%")
                ->orWhere('nom', 'ilike', "%{$needle}%")
                ->orWhere('prenom', 'ilike', "%{$needle}%")
                ->orWhere('email', 'ilike', "%{$needle}%"));
        }
    }

    private static function statusColor(?string $status): string
    {
        return match ($status) {
            Payment::STATUS_PAID    => 'success',
            Payment::STATUS_PENDING => 'warning text-dark',
            Payment::STATUS_INIT    => 'secondary',
            Payment::STATUS_FAILED  => 'danger',
            default                 => 'secondary',
        };
    }
}
