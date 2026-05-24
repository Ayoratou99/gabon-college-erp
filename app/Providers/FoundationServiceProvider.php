<?php

declare(strict_types=1);

namespace App\Providers;

use App\Foundation\Http\Middleware\RequirePermission;
use App\Foundation\Identity\Contracts\UserScopeResolver;
use App\Foundation\Identity\DefaultUserScopeResolver;
use App\Foundation\Permissions\Contracts\PermissionHolder;
use App\Foundation\Permissions\PermissionChecker;
use App\Foundation\Permissions\PermissionRegistry;
use App\Foundation\Permissions\Resolvers\OwnCenterResolver;
use App\Foundation\Permissions\Resolvers\OwnRegionResolver;
use App\Foundation\Permissions\Resolvers\OwnResolver;
use App\Foundation\Permissions\Resolvers\OwnSessionResolver;
use App\Foundation\Permissions\Resolvers\WildcardResolver;
use App\Foundation\Permissions\ScopedQuery;
use App\Foundation\Permissions\ScopeResolverRegistry;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * Wires up everything in app/Foundation:
 *
 *   - Scope resolver registry (registered as singleton, populated with the
 *     five built-in resolvers; modules may add their own in their own provider).
 *   - PermissionChecker + ScopedQuery + PermissionRegistry singletons.
 *   - `perm` route middleware alias.
 *   - Gate::before(): delegates string-form permissions ("edit:candidats:any")
 *     to the PermissionChecker, so the standard $user->can(...) API works.
 */
final class FoundationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/permissions.php', 'permissions');

        $this->app->singleton(ScopeResolverRegistry::class, function (): ScopeResolverRegistry {
            $registry = new ScopeResolverRegistry();
            $registry->register(new WildcardResolver());
            $registry->register(new OwnResolver());
            $registry->register(new OwnCenterResolver());
            $registry->register(new OwnRegionResolver());
            $registry->register(new OwnSessionResolver());
            return $registry;
        });

        $this->app->singleton(PermissionChecker::class);
        $this->app->singleton(ScopedQuery::class);
        $this->app->singleton(PermissionRegistry::class);

        // Default null-object — overridden by Concours module's CandidatCentreResolver
        // once that module boots. Binding here (not booting) ensures a sane fallback
        // for unit tests that don't load Concours.
        $this->app->bind(UserScopeResolver::class, DefaultUserScopeResolver::class);
    }

    public function boot(Router $router, PermissionChecker $checker): void
    {
        $router->aliasMiddleware('perm', RequirePermission::class);

        // Delegate string-pattern abilities to the PermissionChecker so the
        // idiomatic $user->can('edit:candidats:any', $candidat) syntax works.
        Gate::before(function (?Authenticatable $user, string $ability, array $arguments = []) use ($checker) {
            if (substr_count($ability, ':') !== 2) {
                return null; // not our format → let other gate logic decide
            }
            if (! $user instanceof PermissionHolder) {
                return null;
            }
            $target = $arguments[0] ?? null;
            return $checker->can($user, $ability, is_object($target) && method_exists($target, 'getKey') ? $target : null)
                ? true
                : null; // returning null lets a Policy still allow it; explicit false would short-circuit deny
        });
    }
}
