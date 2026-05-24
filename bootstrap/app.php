<?php

declare(strict_types=1);

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Modules\UserManagement\Http\Middleware\EnsureTwoFactorVerified;
use Modules\UserManagement\Http\Middleware\VerifyRecaptcha;
use App\Foundation\Http\Middleware\RequirePermission;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'perm'       => RequirePermission::class,
            'recaptcha'  => VerifyRecaptcha::class,
            'twofactor'  => EnsureTwoFactorVerified::class,
        ]);

        $middleware->web(append: [
            // Per-request observability could go here later.
        ]);

        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontReport([
            Modules\UserManagement\Exceptions\AuthenticationException::class,
            Modules\UserManagement\Exceptions\LoginThrottledException::class,
        ]);
    })->create();
