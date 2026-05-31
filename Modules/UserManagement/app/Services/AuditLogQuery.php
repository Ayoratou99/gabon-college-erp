<?php

declare(strict_types=1);

namespace Modules\UserManagement\Services;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Collection;
use Modules\Concours\Models\Candidat;
use Modules\Parametrage\Models\Setting;
use Modules\UserManagement\Models\User;

/**
 * Cross-table query for the unified audit log.
 *
 *   - candidat_modifications   (dossier edits / decisions, channels: public/admin/system)
 *   - setting_change_logs      (Parametrage value flips)
 *   - login_attempts           (auth events, both succeeded & failed)
 *
 * Each source is projected to a uniform shape:
 *
 *   id              text (`<prefix>:<uuid>` so it stays unique across UNIONs)
 *   source          text ('candidat' | 'setting' | 'login')
 *   event_type      text   — friendly tag (channel name / 'setting' / 'login_success'|'login_failure')
 *   at              timestamp — event time, NOT NULL (used for ORDER BY)
 *   actor_user_id   uuid nullable — who did it (null for console / anonymous logins)
 *   target_kind     text   — 'candidat' / 'setting' / 'user'
 *   target_id       text   — uuid of the target row (candidat / setting / user)
 *   field           text   — the column or "field" that changed (e.g. statut, document.acte.review_status)
 *   old_value       text
 *   new_value       text
 *   ip_address      text
 *   reason          text   — free-text context (rejection motif, identifier for failed logins, etc.)
 *
 * Filters are applied on the outer SELECT so each source can use its own
 * indexes; the result set is then ordered by `at DESC` and paginated by
 * the caller via standard Query Builder methods.
 *
 * Actor / target labels are NOT resolved in SQL — that would force the
 * union to join `users` + `candidats` + `settings` three times. Instead
 * we resolve them in PHP on the paginated slice via three small batched
 * lookups (see `hydrate`).
 */
final class AuditLogQuery
{
    public function __construct(
        private readonly ConnectionInterface $db,
    ) {}

    /**
     * Build the unified Query Builder. Accepts filters:
     *
     *   - source         'candidat' | 'setting' | 'login'   (one or null = all)
     *   - from / to      Y-m-d strings
     *   - actor_user_id  uuid
     *   - ip             substring (ILIKE)
     *   - event_type     channel / 'login_success' / 'login_failure' / 'setting'
     *
     * @param array{
     *   source?: ?string,
     *   from?: ?string,
     *   to?: ?string,
     *   actor_user_id?: ?string,
     *   ip?: ?string,
     *   event_type?: ?string,
     *   actor_search?: ?string,
     * } $filters
     */
    public function build(array $filters = []): Builder
    {
        $unioned = $this->db->query()
            ->fromSub($this->candidatModificationsProjection(), 'cm')
            ->unionAll($this->settingChangeLogsProjection())
            ->unionAll($this->loginAttemptsProjection());

        // Wrap the union in an outer SELECT so we can apply WHERE / ORDER
        // BY / LIMIT against the unified shape.
        $query = $this->db->query()
            ->fromSub($unioned, 'audit_log');

        if (! empty($filters['source'])) {
            $query->where('source', $filters['source']);
        }
        if (! empty($filters['event_type'])) {
            $query->where('event_type', $filters['event_type']);
        }
        if (! empty($filters['from'])) {
            $query->where('at', '>=', $filters['from'] . ' 00:00:00');
        }
        if (! empty($filters['to'])) {
            $query->where('at', '<=', $filters['to'] . ' 23:59:59');
        }
        if (! empty($filters['actor_user_id'])) {
            $query->where('actor_user_id', $filters['actor_user_id']);
        }
        if (! empty($filters['ip'])) {
            $query->where('ip_address', 'ilike', '%' . $filters['ip'] . '%');
        }
        if (! empty($filters['actor_search'])) {
            // Name / email search resolves to a set of user ids first so the
            // outer query stays clean. Empty result set = no rows match.
            $ids = User::query()
                ->where(function ($q) use ($filters): void {
                    $needle = '%' . $filters['actor_search'] . '%';
                    $q->where('nom', 'ilike', $needle)
                      ->orWhere('prenom', 'ilike', $needle)
                      ->orWhere('email', 'ilike', $needle);
                })
                ->pluck('id')
                ->all();
            if ($ids === []) {
                $query->whereRaw('1 = 0');
            } else {
                $query->whereIn('actor_user_id', $ids);
            }
        }

        return $query->orderBy('at', 'desc');
    }

    /**
     * Resolve human-friendly actor + target labels onto a paginated slice,
     * in 3 batched queries (one per resolvable table).
     *
     * @param  iterable<int, \stdClass>  $rows
     * @return Collection<int, array<string, mixed>>
     */
    public function hydrate(iterable $rows): Collection
    {
        $rows = collect($rows);
        $userIds      = $rows->pluck('actor_user_id')->filter()->unique();
        $candidatIds  = $rows->where('target_kind', 'candidat')->pluck('target_id')->filter()->unique();
        $settingIds   = $rows->where('target_kind', 'setting')->pluck('target_id')->filter()->unique();
        $targetUserIds = $rows->where('target_kind', 'user')->pluck('target_id')->filter()->unique();

        $users     = User::query()->whereIn('id', $userIds->merge($targetUserIds)->all())
            ->get(['id', 'nom', 'prenom', 'email'])->keyBy('id');
        $candidats = Candidat::query()->whereIn('id', $candidatIds->all())
            ->get(['id', 'matricule_public', 'nom', 'prenom'])->keyBy('id');
        $settings  = Setting::query()->whereIn('id', $settingIds->all())
            ->get(['id', 'key', 'label', 'category'])->keyBy('id');

        return $rows->map(function (object $r) use ($users, $candidats, $settings, $targetUserIds): array {
            $actor = $r->actor_user_id ? $users->get($r->actor_user_id) : null;
            $target = match ($r->target_kind) {
                'candidat' => $candidats->get($r->target_id),
                'setting'  => $settings->get($r->target_id),
                'user'     => $users->get($r->target_id),
                default    => null,
            };

            return [
                'id'             => $r->id,
                'source'         => $r->source,
                'event_type'     => $r->event_type,
                'at'             => $r->at,
                'actor_user_id'  => $r->actor_user_id,
                'actor_label'    => $actor
                    ? trim(($actor->prenom ?? '') . ' ' . ($actor->nom ?? '')) . ' (' . ($actor->email ?? '?') . ')'
                    : ($r->source === 'login' ? '— (anonyme)' : 'Console / système'),
                'target_kind'    => $r->target_kind,
                'target_id'      => $r->target_id,
                'target_label'   => $this->targetLabel($r->target_kind, $target, $r->reason),
                'field'          => $r->field,
                'old_value'      => $r->old_value,
                'new_value'      => $r->new_value,
                'ip_address'     => $r->ip_address,
                'reason'         => $r->reason,
            ];
        });
    }

    // ------------------------------------------------------- projections

    private function candidatModificationsProjection(): Builder
    {
        return $this->db->table('candidat_modifications')
            ->select([
                new Expression("'cm:' || id::text AS id"),
                new Expression("'candidat'::text AS source"),
                new Expression("channel AS event_type"),
                new Expression("changed_at AS at"),
                new Expression("user_id AS actor_user_id"),
                new Expression("'candidat'::text AS target_kind"),
                new Expression("candidat_id::text AS target_id"),
                'field',
                'old_value',
                'new_value',
                'ip_address',
                'reason',
            ]);
    }

    private function settingChangeLogsProjection(): Builder
    {
        return $this->db->table('setting_change_logs')
            ->select([
                new Expression("'sl:' || id::text AS id"),
                new Expression("'setting'::text AS source"),
                new Expression("'setting'::text AS event_type"),
                new Expression("changed_at AS at"),
                new Expression("user_id AS actor_user_id"),
                new Expression("'setting'::text AS target_kind"),
                new Expression("setting_id::text AS target_id"),
                new Expression("NULL::text AS field"),
                'old_value',
                'new_value',
                'ip_address',
                new Expression("NULL::text AS reason"),
            ]);
    }

    private function loginAttemptsProjection(): Builder
    {
        return $this->db->table('login_attempts')
            ->select([
                new Expression("'la:' || id::text AS id"),
                new Expression("'login'::text AS source"),
                new Expression("CASE WHEN succeeded THEN 'login_success'::text ELSE 'login_failure'::text END AS event_type"),
                new Expression("attempted_at AS at"),
                new Expression("user_id AS actor_user_id"),
                new Expression("'user'::text AS target_kind"),
                new Expression("COALESCE(user_id::text, '') AS target_id"),
                new Expression("NULL::text AS field"),
                new Expression("NULL::text AS old_value"),
                new Expression("failure_reason AS new_value"),
                'ip_address',
                new Expression("identifier AS reason"),
            ]);
    }

    private function targetLabel(string $kind, ?object $target, ?string $reasonFallback): string
    {
        if ($target === null) {
            return $kind === 'user' && $reasonFallback !== null
                ? "Identifiant : {$reasonFallback}"  // login_attempts where no user existed
                : '— (cible inconnue)';
        }
        return match ($kind) {
            'candidat' => sprintf('%s — %s %s',
                $target->matricule_public ?? '?', $target->nom ?? '', $target->prenom ?? ''),
            'setting'  => sprintf('%s [%s]',
                $target->label ?: $target->key, $target->category ?? '?'),
            'user'     => trim(($target->prenom ?? '') . ' ' . ($target->nom ?? ''))
                . ' (' . ($target->email ?? '?') . ')',
            default    => '— (cible inconnue)',
        };
    }
}
