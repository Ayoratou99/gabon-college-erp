<?php

declare(strict_types=1);

namespace Modules\Reporting\Services;

use App\Foundation\Permissions\Contracts\PermissionHolder;
use App\Foundation\Permissions\ScopedQuery;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Database\Eloquent\Builder;
use Modules\Concours\Models\Candidat;
use Modules\Concours\Models\ConcoursSession;
use Modules\Concours\Models\Payment;

/**
 * Read-only aggregation queries powering the Reporting dashboard.
 *
 * Every method takes an optional `?PermissionHolder $user` and applies the
 * standard RBAC scope (`view`, `candidats`) — so a chef-centre's call to
 * `byCentre()` returns just their centre, while DG/DE see all centres.
 *
 * Results are cached per (session_id, user_id) for 2 minutes so the
 * dashboard's 6 panels render with one DB roundtrip each on first load,
 * zero on the next.
 */
final class StatisticsService
{
    public function __construct(
        private readonly ScopedQuery $scoped,
        private readonly CacheRepository $cache,
    ) {}

    /** @return array{total:int, pending:int, accepted:int, paid:int, rejected:int, admitted:int} */
    public function summary(?ConcoursSession $session = null, ?PermissionHolder $user = null): array
    {
        return $this->remember('summary', $session, $user, function () use ($session, $user): array {
            $byStatus = $this->base($session, $user)
                ->selectRaw('statut, COUNT(*) AS n')
                ->groupBy('statut')
                ->pluck('n', 'statut');

            return [
                'total'    => (int) $byStatus->sum(),
                'pending'  => (int) ($byStatus['non']    ?? 0),
                'accepted' => (int) ($byStatus['oui']    ?? 0),
                'paid'     => (int) ($byStatus['valid']  ?? 0),
                'rejected' => (int) ($byStatus['rejete'] ?? 0),
                'admitted' => (int) ($byStatus['admis']  ?? 0),
            ];
        });
    }

    /** @return list<array{label:string, value:int}> */
    public function byStatus(?ConcoursSession $session = null, ?PermissionHolder $user = null): array
    {
        $labels = [
            'non'    => 'En cours',
            'oui'    => 'Accepté',
            'valid'  => 'Payé',
            'rejete' => 'Rejeté',
            'admis'  => 'Admis',
        ];
        $counts = $this->base($session, $user)
            ->selectRaw('statut, COUNT(*) AS n')
            ->groupBy('statut')
            ->pluck('n', 'statut');

        return collect($labels)
            ->map(static fn (string $label, string $code) => [
                'label' => $label,
                'value' => (int) ($counts[$code] ?? 0),
            ])
            ->values()->all();
    }

    /** @return list<array{label:string, value:int}> */
    public function byCentre(?ConcoursSession $session = null, ?PermissionHolder $user = null): array
    {
        return $this->remember('by-centre', $session, $user, function () use ($session, $user): array {
            return $this->base($session, $user)
                ->join('centres', 'centres.id', '=', 'candidats.centre_id')
                ->selectRaw('centres.nom, COUNT(*) AS n')
                ->groupBy('centres.id', 'centres.nom')
                ->orderByDesc('n')
                ->limit(15)
                ->get()
                ->map(static fn ($row) => ['label' => (string) $row->nom, 'value' => (int) $row->n])
                ->all();
        });
    }

    /** @return list<array{label:string, value:int}> */
    public function bySection(?ConcoursSession $session = null, ?PermissionHolder $user = null): array
    {
        return $this->remember('by-section', $session, $user, function () use ($session, $user): array {
            return $this->base($session, $user)
                ->join('sections', 'sections.id', '=', 'candidats.section_premier_choix_id')
                ->selectRaw('sections.code, sections.nom, COUNT(*) AS n')
                ->groupBy('sections.id', 'sections.code', 'sections.nom')
                ->orderByDesc('n')
                ->get()
                ->map(static fn ($row) => ['label' => "{$row->code} — {$row->nom}", 'value' => (int) $row->n])
                ->all();
        });
    }

    /** @return list<array{label:string, value:int}> */
    public function bySeriesBac(?ConcoursSession $session = null, ?PermissionHolder $user = null): array
    {
        return $this->remember('by-series-bac', $session, $user, function () use ($session, $user): array {
            return $this->base($session, $user)
                ->join('series_bac', 'series_bac.id', '=', 'candidats.serie_bac_id')
                ->selectRaw('series_bac.nom, COUNT(*) AS n')
                ->groupBy('series_bac.id', 'series_bac.nom')
                ->orderByDesc('n')
                ->get()
                ->map(static fn ($row) => ['label' => (string) $row->nom, 'value' => (int) $row->n])
                ->all();
        });
    }

    /** @return array{male:int, female:int, total:int} */
    public function bySex(?ConcoursSession $session = null, ?PermissionHolder $user = null): array
    {
        $rows = $this->base($session, $user)
            ->selectRaw('sexe, COUNT(*) AS n')
            ->groupBy('sexe')
            ->pluck('n', 'sexe');

        $male   = (int) ($rows['M'] ?? 0);
        $female = (int) ($rows['F'] ?? 0);

        return ['male' => $male, 'female' => $female, 'total' => $male + $female];
    }

    /**
     * Inscriptions per day over the last $days days.
     *
     * @return list<array{label:string, value:int}>
     */
    public function registrationsTimeline(int $days = 30, ?ConcoursSession $session = null, ?PermissionHolder $user = null): array
    {
        return $this->remember("timeline-{$days}", $session, $user, function () use ($days, $session, $user): array {
            $since = now()->subDays($days - 1)->startOfDay();
            $counts = $this->base($session, $user)
                ->where('created_at', '>=', $since)
                ->selectRaw("DATE(created_at) AS d, COUNT(*) AS n")
                ->groupBy('d')
                ->pluck('n', 'd');

            $series = [];
            for ($i = 0; $i < $days; $i++) {
                $d = $since->copy()->addDays($i)->format('Y-m-d');
                $series[] = ['label' => $d, 'value' => (int) ($counts[$d] ?? 0)];
            }
            return $series;
        });
    }

    /** @return array{paid_amount:int, paid_count:int, pending_count:int} */
    public function paymentsSummary(?ConcoursSession $session = null): array
    {
        $query = Payment::query();
        if ($session !== null) {
            $query->where('concours_session_id', $session->id);
        }

        $byStatus = (clone $query)
            ->selectRaw('status, COUNT(*) AS n, COALESCE(SUM(amount), 0) AS s')
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        return [
            'paid_amount'   => (int) ($byStatus[Payment::STATUS_PAID]->s ?? 0),
            'paid_count'    => (int) ($byStatus[Payment::STATUS_PAID]->n ?? 0),
            'pending_count' => (int) ($byStatus[Payment::STATUS_PENDING]->n ?? 0),
        ];
    }

    // ---------------------------------------------------------------- internals

    private function base(?ConcoursSession $session, ?PermissionHolder $user): Builder
    {
        $query = Candidat::query();
        if ($session !== null) {
            $query->where('concours_session_id', $session->id);
        }
        if ($user !== null) {
            $query = $this->scoped->apply($query, $user, 'view', 'candidats');
        }
        return $query;
    }

    private function remember(string $bucket, ?ConcoursSession $session, ?PermissionHolder $user, \Closure $compute): mixed
    {
        if (! config('reporting.cache.enabled', true)) {
            return $compute();
        }

        $key = sprintf(
            '%s%s:%s:%s',
            (string) config('reporting.cache.prefix', 'cuk:reporting:'),
            $bucket,
            $session?->id ?? '_all',
            $user?->getKey() ?? '_any',
        );

        return $this->cache
            ->store((string) config('reporting.cache.store'))
            ->remember($key, (int) config('reporting.cache.ttl', 120), $compute);
    }
}
