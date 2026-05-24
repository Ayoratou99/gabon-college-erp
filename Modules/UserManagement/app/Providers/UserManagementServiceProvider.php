<?php

declare(strict_types=1);

namespace Modules\UserManagement\Providers;

use App\Foundation\Permissions\PermissionRegistry;
use Illuminate\Support\ServiceProvider;
use PragmaRX\Google2FA\Google2FA;

final class UserManagementServiceProvider extends ServiceProvider
{
    private const string MODULE = 'UserManagement';

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/config.php', 'usermanagement');

        // Bind Google2FA as singleton (stateless, safe to share).
        $this->app->singleton(Google2FA::class, fn (): Google2FA => new Google2FA());
    }

    public function boot(PermissionRegistry $registry): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'usermanagement');
        $this->loadTranslationsFrom(__DIR__ . '/../../resources/lang', 'usermanagement');
        $this->loadRoutesFrom(__DIR__ . '/../../routes/web.php');

        // Declare the permission catalog this module exposes — fails fast at
        // boot if any pattern is malformed.
        $registry->declare(self::MODULE, [
            // user-management itself
            'view:users:*',
            'view:users:own',
            'create:users:*',
            'edit:users:*',
            'edit:users:own',
            'delete:users:*',

            'view:roles:*',
            'create:roles:*',
            'edit:roles:*',
            'delete:roles:*',

            'view:permissions:*',
            'view:login_attempts:*',
        ]);
    }
}
