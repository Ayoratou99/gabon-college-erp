<?php

declare(strict_types=1);

namespace Modules\Evaluations\Providers;

use Illuminate\Support\ServiceProvider;

final class EvaluationsServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
$this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');
    }
}
