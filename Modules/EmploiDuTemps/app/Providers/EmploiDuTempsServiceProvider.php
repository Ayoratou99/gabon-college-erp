<?php

declare(strict_types=1);

namespace Modules\EmploiDuTemps\Providers;

use Illuminate\Support\ServiceProvider;

final class EmploiDuTempsServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
$this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');
    }
}
