<?php

declare(strict_types=1);

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Modules\UserManagement\Http\Middleware\EnsureActiveRole;
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
            'perm'         => RequirePermission::class,
            'recaptcha'    => VerifyRecaptcha::class,
            'twofactor'    => EnsureTwoFactorVerified::class,
            'active.role'  => EnsureActiveRole::class,
        ]);

        $middleware->web(append: [
            // Per-request observability could go here later.
        ]);

        // Server-to-server callbacks carry NO CSRF token — they're authenticated
        // by the encrypted external_reference, not a browser session. Without this
        // the eBilling payment callback is rejected with HTTP 419 (CSRF mismatch).
        // The route-level withoutMiddleware() isn't always honoured (route cache /
        // custom CSRF class), so we exempt the URI globally here.
        $middleware->validateCsrfTokens(except: [
            'payment/ebilling/callback',
        ]);

        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontReport([
            Modules\UserManagement\Exceptions\AuthenticationException::class,
            Modules\UserManagement\Exceptions\LoginThrottledException::class,
        ]);
    })->create();
