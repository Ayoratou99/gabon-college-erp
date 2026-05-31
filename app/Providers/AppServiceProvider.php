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

        // Production URL generation.
        //
        // On shared hosting the app often lives under a sub-path
        // (https://host/concours-…/) and the web server's SCRIPT_NAME does not
        // carry that prefix, so Laravel's automatic base-path detection yields
        // domain-root links. We therefore pin the generator's root to APP_URL
        // (which MUST include the sub-path) so every route()/url() is correct
        // regardless of the host's rewrite quirks. asset() additionally honours
        // ASSET_URL. Both are set in .env.
        if (app()->isProduction()) {
            URL::forceScheme('https');
            if ($root = trim((string) config('app.url'))) {
                URL::forceRootUrl($root);
            }
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
