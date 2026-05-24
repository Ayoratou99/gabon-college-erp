<?php

declare(strict_types=1);

namespace Modules\AcademicStructure\Providers;

use App\Foundation\Permissions\PermissionRegistry;
use Illuminate\Support\ServiceProvider;
use Modules\AcademicStructure\Services\AcademicResourceRegistry;

final class AcademicStructureServiceProvider extends ServiceProvider
{
    private const string MODULE = 'AcademicStructure';

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/config.php', 'academic-structure');
        $this->app->singleton(AcademicResourceRegistry::class);
    }

    public function boot(PermissionRegistry $registry, AcademicResourceRegistry $resources): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');
        $this->loadRoutesFrom(__DIR__ . '/../../routes/web.php');

        $permissions = [];
        foreach ($resources->slugs() as $slug) {
            $resource = $resources->resourceFor($slug);
            foreach (['view', 'create', 'edit', 'delete'] as $action) {
                $permissions[] = "{$action}:{$resource}:*";
            }
        }
        $registry->declare(self::MODULE, $permissions);
    }
}
