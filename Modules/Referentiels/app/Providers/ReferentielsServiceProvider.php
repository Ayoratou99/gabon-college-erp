<?php

declare(strict_types=1);

namespace Modules\Referentiels\Providers;

use App\Foundation\Permissions\PermissionRegistry;
use Illuminate\Support\ServiceProvider;
use Modules\Referentiels\Services\ReferentielRegistry;

final class ReferentielsServiceProvider extends ServiceProvider
{
    private const string MODULE = 'Referentiels';

    public function register(): void
    {
        $this->app->singleton(ReferentielRegistry::class);
    }

    public function boot(PermissionRegistry $registry, ReferentielRegistry $referentiels): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');
        $this->loadRoutesFrom(__DIR__ . '/../../routes/web.php');

        $permissions = [];
        foreach ($referentiels->slugs() as $slug) {
            $resource = $referentiels->resourceFor($slug);
            foreach (['view', 'create', 'edit', 'delete'] as $action) {
                $permissions[] = "{$action}:{$resource}:*";
            }
        }
        $registry->declare(self::MODULE, $permissions);
    }
}
