<?php

declare(strict_types=1);

namespace App\Providers;

use App\View\Composers\AdminMenuComposer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
    }

    public function boot(): void
    {
        // Forbid lazy loading in non-prod so N+1 surfaces immediately.
        Model::preventLazyLoading(! app()->isProduction());
        Model::preventSilentlyDiscardingAttributes(! app()->isProduction());
        Model::preventAccessingMissingAttributes(! app()->isProduction());

        // Enforce HTTPS in production.
        if (app()->isProduction()) {
            URL::forceScheme('https');
        }

        // Polymorphic morph map — kept here as the central registry so
        // changing a class name doesn't silently break stored morph rows.
        Relation::enforceMorphMap([
            'user' => \Modules\UserManagement\Models\User::class,
            'role' => \Modules\UserManagement\Models\Role::class,
        ]);

        // Admin sidebar — composer runs only for views that need it,
        // filtered by what the current user can see (RBAC).
        View::composer(
            ['layouts.admin', 'partials.admin.sidebar'],
            AdminMenuComposer::class,
        );
    }
}
