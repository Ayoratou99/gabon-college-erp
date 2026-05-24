<?php

declare(strict_types=1);

namespace App\Foundation\Http\Middleware;

use App\Foundation\Permissions\Contracts\PermissionHolder;
use App\Foundation\Permissions\PermissionChecker;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route middleware: ->middleware('perm:edit:candidats:any')
 *
 * Resolves the authenticated user, asserts they hold the required permission,
 * and threads through. The check is generic — no target row is bound here.
 * For row-scoped operations, use Policy::authorize() inside the FormRequest.
 *
 * Usage:
 *
 *     Route::get('/candidats', [CandidatController::class, 'index'])
 *         ->middleware('perm:view:candidats:any');
 *
 *     Route::post('/candidats/{c}/validate', [CandidatController::class, 'validate'])
 *         ->middleware('perm:validate:candidats:any');
 */
final class RequirePermission
{
    public function __construct(
        private readonly PermissionChecker $checker,
    ) {}

    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        if ($user === null) {
            throw new AuthenticationException();
        }

        if (! $user instanceof PermissionHolder) {
            throw new AuthorizationException(
                'Authenticated user does not implement PermissionHolder.'
            );
        }

        if (! $this->checker->can($user, $permission)) {
            throw new AuthorizationException(
                "Missing permission: {$permission}"
            );
        }

        return $next($request);
    }
}
