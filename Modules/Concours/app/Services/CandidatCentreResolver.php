<?php

declare(strict_types=1);

namespace Modules\Concours\Services;

use App\Foundation\Identity\Contracts\UserScopeResolver;
use App\Foundation\Permissions\Contracts\PermissionHolder;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Modules\Concours\Models\ChefCentreAssignment;
use Modules\Concours\Models\ConcoursSession;

/**
 * Concours-aware implementation of UserScopeResolver.
 *
 *   - accessibleCentreIds() returns the centres the user is chef of for
 *     either the user's `current_session_id` (set at login) OR, if that's
 *     null, the currently-active concours session.
 *   - accessibleRegionIds() derives from the provinces of those centres.
 *
 * Results are short-cached (Redis, 60 s) because the lookup happens on
 * every protected request and a chef's assignment doesn't change mid-day.
 */
final class CandidatCentreResolver implements UserScopeResolver
{
    private const CACHE_TTL = 60;

    public function __construct(
        private readonly CacheRepository $cache,
    ) {}

    public function accessibleCentreIds(PermissionHolder $user): array
    {
        $sessionId = $this->resolveSessionId($user);
        if ($sessionId === null) {
            return [];
        }

        return $this->cache->remember(
            "cuk:user:{$user->getKey()}:centres:{$sessionId}",
            self::CACHE_TTL,
            fn (): array => ChefCentreAssignment::query()
                ->where('user_id', $user->getKey())
                ->where('concours_session_id', $sessionId)
                ->pluck('centre_id')
                ->map(static fn ($id): string => (string) $id)
                ->all(),
        );
    }

    public function accessibleRegionIds(PermissionHolder $user): array
    {
        $centreIds = $this->accessibleCentreIds($user);
        if ($centreIds === []) {
            return [];
        }

        return $this->cache->remember(
            "cuk:user:{$user->getKey()}:regions",
            self::CACHE_TTL,
            fn (): array => \Modules\Concours\Models\Centre::query()
                ->whereIn('id', $centreIds)
                ->whereNotNull('province_id')
                ->pluck('province_id')
                ->unique()
                ->map(static fn ($id): string => (string) $id)
                ->values()
                ->all(),
        );
    }

    private function resolveSessionId(PermissionHolder $user): ?string
    {
        if ($user->currentSessionId() !== null) {
            return $user->currentSessionId();
        }
        return ConcoursSession::active()?->getKey();
    }
}
