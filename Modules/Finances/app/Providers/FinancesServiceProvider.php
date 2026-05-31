<?php

declare(strict_types=1);

namespace Modules\Finances\Providers;

use Illuminate\Support\ServiceProvider;

final class FinancesServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
$this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');
    }
}
