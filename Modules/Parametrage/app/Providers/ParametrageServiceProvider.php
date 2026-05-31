<?php

declare(strict_types=1);

namespace Modules\Parametrage\Providers;

use App\Foundation\Permissions\PermissionRegistry;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Modules\Parametrage\Models\Setting;
use Modules\Parametrage\Policies\SettingPolicy;
use Modules\Parametrage\Services\SettingsService;
use Modules\Parametrage\Services\SettingValueCaster;

final class ParametrageServiceProvider extends ServiceProvider
{
    private const string MODULE = 'Parametrage';

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/config.php', 'parametrage');

        $this->app->singleton(SettingValueCaster::class);
        $this->app->singleton(SettingsService::class);
    }

    public function boot(PermissionRegistry $registry, ViewFactory $viewFactory): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');
        $this->loadRoutesFrom(__DIR__ . '/../../routes/web.php');
        // loadViewsFrom() queues via callAfterResolving which fires unreliably
        // when 'view' is resolved via a contract alias before boot. Register
        // the namespace directly so the hint is always present.
        View::addNamespace('parametrage', __DIR__ . '/../../resources/views');

        Gate::policy(Setting::class, SettingPolicy::class);

        $registry->declare(self::MODULE, [
            'view:parametrage:*',
            'edit:parametrage:*',
            'view:parametrage_audit:*',
        ]);

        // Share the public-settings map with every view so blade templates can
        // call $settings['site.banner.title'] without controllers having to
        // inject the service every time.
        $viewFactory->composer('*', function ($view): void {
            $view->with('settings', app(SettingsService::class)->publicMap());
        });
    }
}
