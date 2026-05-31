<?php

declare(strict_types=1);

namespace Modules\UserManagement\Http\Controllers\Admin\Pages;

use App\Foundation\Http\DataTables\DataTablesQuery;
use App\Foundation\Permissions\PermissionChecker;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\UserManagement\Models\LoginAttempt;
use Symfony\Component\HttpFoundation\Response;

/**
 * Audit feed of login attempts (success + failure). DataTables-backed,
 * filterable by status, scoped through view:login_attempts:*.
 */
final class LoginAttemptPageController extends Controller
{
    public function __construct(
        private readonly PermissionChecker $checker,
    ) {}

    public function index(Request $request): View
    {
        $this->ensure($request);
        return view('usermanagement::admin.login-attempts.index');
    }

    public function data(Request $request): JsonResponse
    {
        $this->ensure($request);

        $query = LoginAttempt::query()->with('user:id,nom,prenom,telephone');

        return DataTablesQuery::for($query)
            ->searchable(['identifier', 'ip_address', 'user_agent', 'failure_reason'])
            ->orderable([
                'attempted_at' => 'attempted_at',
                'identifier'   => 'identifier',
                'succeeded'    => 'succeeded',
            ])
            ->filterUsing(function (Builder $q, array $filters): void {
                if (($filters['outcome'] ?? '') === 'failed')    { $q->where('succeeded', false); }
                if (($filters['outcome'] ?? '') === 'succeeded') { $q->where('succeeded', true); }
                if (! empty($filters['days'])) {
                    $q->where('attempted_at', '>=', now()->subDays((int) $filters['days']));
                }
            })
            ->transform(fn (LoginAttempt $a): array => [
                'id'           => $a->id,
                'attempted_at' => $a->attempted_at?->format('d/m/Y H:i:s') ?? '—',
                'identifier'   => e($a->identifier ?? '—'),
                'user'         => $a->user
                    ? e($a->user->nom . ' ' . $a->user->prenom)
                    : '<span class="text-muted">inconnu</span>',
                'ip_address'   => '<code>' . e($a->ip_address ?? '—') . '</code>',
                'succeeded'    => $a->succeeded
                    ? '<span class="badge bg-success-subtle text-success-emphasis"><i class="fas fa-check me-1"></i>ok</span>'
                    : '<span class="badge bg-danger-subtle text-danger-emphasis"><i class="fas fa-xmark me-1"></i>échec</span>',
                'reason'       => e($a->failure_reason ?? '—'),
                'user_agent'   => '<span class="small text-muted">' . e(mb_strimwidth($a->user_agent ?? '—', 0, 60, '…')) . '</span>',
            ])
            ->respond($request);
    }

    private function ensure(Request $request): void
    {
        if (! $this->checker->can($request->user(), 'view:login_attempts:*')) {
            abort(Response::HTTP_FORBIDDEN);
        }
    }
}
