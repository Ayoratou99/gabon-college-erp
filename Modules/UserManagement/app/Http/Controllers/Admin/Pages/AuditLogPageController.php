<?php

declare(strict_types=1);

namespace Modules\UserManagement\Http\Controllers\Admin\Pages;

use App\Foundation\Permissions\PermissionChecker;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\UserManagement\Models\Role;
use Modules\UserManagement\Models\User;
use Modules\UserManagement\Services\AuditLogQuery;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Unified "Journal d'audit" admin.
 *
 *   GET  /admin/audit-log             → page (filter form + DataTables shell)
 *   POST /admin/audit-log/data        → DataTables server-side feed
 *   GET  /admin/audit-log/export.csv  → streaming CSV honouring filters
 *
 * Reads from the cross-source AuditLogQuery (candidat_modifications +
 * setting_change_logs + login_attempts). No schema change.
 *
 * Permission: `view:audit_log:*` (DG / DE / super-admin). Chef-centre
 * has no business browsing the platform-wide audit feed.
 */
final class AuditLogPageController extends Controller
{
    public function __construct(
        private readonly PermissionChecker $checker,
        private readonly AuditLogQuery $audit,
    ) {}

    public function index(Request $request): View
    {
        $this->ensure($request);

        // Pre-resolve a small list of admin-ish users for the "actor"
        // dropdown — keeps the picker fast and we don't expose candidat
        // accounts to the audit filter (their actions show up under the
        // 'candidat' role channel anyway).
        $candidatRoleId = Role::query()->where('code', 'candidat')->value('id');
        $actorUsersQuery = User::query()->orderBy('nom')->orderBy('prenom');
        if ($candidatRoleId !== null) {
            $actorUsersQuery->whereDoesntHave('roles', fn ($q) => $q->where('roles.id', $candidatRoleId));
        }

        return view('usermanagement::admin.audit-log.index', [
            'actorUsers' => $actorUsersQuery->get(['id', 'nom', 'prenom', 'email']),
            'sources'    => [
                'candidat' => 'Dossier candidat',
                'setting'  => 'Paramètre',
                'login'    => 'Connexion',
            ],
            'eventTypes' => [
                // Channels for `candidat_modifications`.
                'public'        => 'Modification (candidat)',
                'admin'         => 'Modification (admin)',
                'system'        => 'Modification (système)',
                // Setting + login.
                'setting'       => 'Changement de paramètre',
                'login_success' => 'Connexion réussie',
                'login_failure' => 'Connexion échouée',
            ],
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $this->ensure($request);

        $draw   = (int) $request->input('draw', 1);
        $start  = max(0, (int) $request->input('start', 0));
        $length = (int) $request->input('length', 25);
        $length = $length < 0 ? 200 : min(max($length, 1), 200);

        $filters = $this->filtersFrom($request);
        $base    = $this->audit->build($filters);

        // The unified query is already a `fromSub(...)`, so getCountForPagination
        // wraps it in `SELECT count(*) FROM (...)` and yields a correct total
        // against the filtered UNION.
        $total = (int) (clone $base)->getCountForPagination();

        $rows = $base->skip($start)->take($length)->get();

        $hydrated = $this->audit->hydrate($rows);

        return response()->json([
            'draw'            => $draw,
            'recordsTotal'    => $total,
            'recordsFiltered' => $total,
            'data'            => $hydrated->map(fn (array $r): array => [
                'at'           => $this->formatTimestamp($r['at']),
                'source'       => $this->sourceBadge($r['source']),
                'event_type'   => $this->eventBadge($r['event_type']),
                'actor'        => e($r['actor_label']),
                'target'       => e($r['target_label']),
                'field'        => $r['field'] ? '<code class="small">' . e($r['field']) . '</code>' : '<span class="text-muted">—</span>',
                'change'       => $this->formatChange($r['old_value'], $r['new_value']),
                'ip'           => $r['ip_address'] ? '<code class="small">' . e($r['ip_address']) . '</code>' : '<span class="text-muted">—</span>',
            ])->all(),
        ]);
    }

    /**
     * Stream a CSV of the (filtered) audit feed. We page through the result
     * in 500-row chunks so memory stays flat even for 100k-row exports.
     */
    public function export(Request $request): StreamedResponse
    {
        $this->ensure($request);

        $filters = $this->filtersFrom($request);
        $base    = $this->audit->build($filters);

        $filename = 'journal-audit-' . now()->format('Y-m-d-Hi') . '.csv';

        return response()->streamDownload(function () use ($base): void {
            $out = fopen('php://output', 'w');
            // BOM so Excel opens the UTF-8 file cleanly.
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, [
                'Date', 'Source', 'Évènement', 'Acteur',
                'Cible', 'Champ', 'Ancienne valeur', 'Nouvelle valeur',
                'IP', 'Motif / Identifiant',
            ], ';', '"', '\\');

            $page = 0;
            $size = 500;
            do {
                $rows = (clone $base)->skip($page * $size)->take($size)->get();
                $hydrated = $this->audit->hydrate($rows);
                foreach ($hydrated as $r) {
                    fputcsv($out, [
                        $this->formatTimestamp($r['at']),
                        $r['source'],
                        $r['event_type'],
                        $r['actor_label'],
                        $r['target_label'],
                        $r['field'] ?? '',
                        $r['old_value'] ?? '',
                        $r['new_value'] ?? '',
                        $r['ip_address'] ?? '',
                        $r['reason'] ?? '',
                    ], ';', '"', '\\');
                }
                $page++;
            } while ($rows->count() === $size);
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=utf-8',
        ]);
    }

    // ----------------------------------------------------- helpers

    /** @return array<string, mixed> */
    private function filtersFrom(Request $request): array
    {
        return [
            'source'        => $request->string('source')->toString() ?: null,
            'event_type'    => $request->string('event_type')->toString() ?: null,
            'from'          => $request->string('from')->toString() ?: null,
            'to'            => $request->string('to')->toString() ?: null,
            'actor_user_id' => $request->string('actor_user_id')->toString() ?: null,
            'ip'            => $request->string('ip')->toString() ?: null,
            'actor_search'  => $request->string('actor_search')->toString() ?: null,
        ];
    }

    private function ensure(Request $request): void
    {
        if (! $this->checker->can($request->user(), 'view:audit_log:*')) {
            abort(Response::HTTP_FORBIDDEN);
        }
    }

    private function formatTimestamp(mixed $at): string
    {
        if ($at === null) {
            return '';
        }
        try {
            return \Carbon\Carbon::parse((string) $at)->format('d/m/Y H:i:s');
        } catch (\Throwable) {
            return (string) $at;
        }
    }

    private function sourceBadge(string $source): string
    {
        return match ($source) {
            'candidat' => '<span class="badge bg-info-subtle text-info-emphasis">Dossier</span>',
            'setting'  => '<span class="badge bg-warning-subtle text-warning-emphasis">Paramètre</span>',
            'login'    => '<span class="badge bg-secondary-subtle text-secondary-emphasis">Connexion</span>',
            default    => '<span class="badge bg-light text-muted">' . e($source) . '</span>',
        };
    }

    private function eventBadge(string $type): string
    {
        return match ($type) {
            'public'        => '<span class="badge bg-info">Candidat</span>',
            'admin'         => '<span class="badge bg-primary">Admin</span>',
            'system'        => '<span class="badge bg-success">Système</span>',
            'setting'       => '<span class="badge bg-warning text-dark">Paramètre</span>',
            'login_success' => '<span class="badge bg-success">✓ OK</span>',
            'login_failure' => '<span class="badge bg-danger">✗ échec</span>',
            default         => '<span class="badge bg-light text-muted">' . e($type) . '</span>',
        };
    }

    private function formatChange(?string $old, ?string $new): string
    {
        $old = $old === null || $old === '' ? '<em class="text-muted">—</em>' : '<code class="text-truncate d-inline-block" style="max-width: 12ch;" title="' . e($old) . '">' . e(\Illuminate\Support\Str::limit($old, 30)) . '</code>';
        $new = $new === null || $new === '' ? '<em class="text-muted">—</em>' : '<code class="text-truncate d-inline-block" style="max-width: 12ch;" title="' . e($new) . '">' . e(\Illuminate\Support\Str::limit($new, 30)) . '</code>';
        return $old . ' <i class="fas fa-arrow-right mx-1 text-muted"></i> ' . $new;
    }
}
