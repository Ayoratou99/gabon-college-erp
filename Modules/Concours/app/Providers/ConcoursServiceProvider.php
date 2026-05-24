<?php

declare(strict_types=1);

namespace Modules\Concours\Providers;

use App\Foundation\Identity\Contracts\UserScopeResolver;
use App\Foundation\Permissions\PermissionRegistry;
use Illuminate\Support\ServiceProvider;
use Modules\Concours\Services\CandidatCentreResolver;

final class ConcoursServiceProvider extends ServiceProvider
{
    private const string MODULE = 'Concours';

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/config.php', 'concours');

        // Override the foundation null-object — now that we have a chef-centre
        // table, real centre membership flows through this resolver.
        $this->app->bind(UserScopeResolver::class, CandidatCentreResolver::class);
    }

    public function boot(PermissionRegistry $registry): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'concours');
        $this->loadRoutesFrom(__DIR__ . '/../../routes/web.php');

        $registry->declare(self::MODULE, [
            // candidats
            'view:candidats:*',
            'view:candidats:own',
            'view:candidats:own_center',
            'view:candidats:own_session',
            'edit:candidats:*',
            'edit:candidats:own',
            'edit:candidats:own_center',
            'validate:candidats:*',
            'validate:candidats:own_center',
            'reject:candidats:*',
            'reject:candidats:own_center',
            'delete:candidats:*',

            // centres + sessions
            'view:centres:*',
            'view:centres:own_center',
            'edit:centres:*',
            'view:sessions:*',
            'view:sessions:own_session',
            'edit:sessions:*',
            'publish:results:*',

            // chef-centre management
            'manage:chef_centre_assignments:*',

            // épreuves
            'view:epreuves:*',
            'view:epreuves:own_session',
            'create:epreuves:*',
            'edit:epreuves:*',
            'delete:epreuves:*',

            // planning
            'view:planning:*',
            'view:planning:own_center',
            'manage:planning:*',

            // notes
            'view:notes:*',
            'view:notes:own_center',
            'enter:notes:*',
            'enter:notes:own_center',
            'lock:notes:*',
        ]);
    }
}
