<?php

declare(strict_types=1);

namespace Modules\Reporting\Providers;

use App\Foundation\Permissions\PermissionRegistry;
use Illuminate\Support\ServiceProvider;
use Modules\Reporting\Services\StatisticsService;

final class ReportingServiceProvider extends ServiceProvider
{
    private const string MODULE = 'Reporting';

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/config.php', 'reporting');
        $this->app->singleton(StatisticsService::class);
    }

    public function boot(PermissionRegistry $registry): void
    {
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'reporting');
        $this->loadRoutesFrom(__DIR__ . '/../../routes/web.php');

        $registry->declare(self::MODULE, [
            'view:reporting:*',
            'view:reporting:own_center',
            'export:reporting:*',
        ]);
    }
}
