<?php

declare(strict_types=1);

namespace Modules\UserManagement\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\UserManagement\Models\User;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate after auth + 2FA — every protected request must have an "active
 * role" pinned in the session.
 *
 *   - 0 roles            → log out + flash "no role assigned"
 *   - 1 role             → auto-pin it, continue
 *   - >1 roles, none picked → redirect to /choisir-role
 *   - >1 roles, picked + still valid → continue
 *   - >1 roles, picked but the role was revoked since → forget + repick
 *
 * Put this AFTER auth and twofactor in the middleware stack. The picker
 * routes themselves must NOT use this middleware (they're the resolution
 * point) — see routes/web.php for the carve-out.
 */
final class EnsureActiveRole
{
    private const SESSION_KEY = 'cuk.active_role_id';

    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user === null) {
            return redirect()->route('login');
        }

        $user->loadMissing('roles:id,code,name');
        $count = $user->roles->count();

        if ($count === 0) {
            // Operators forgot to assign a role. Don't render a half-broken
            // dashboard — kick them back to the login screen with a clear
            // message instead.
            auth()->logout();
            $request->session()->invalidate();
            return redirect()->route('login')
                ->withErrors(['identifier' => 'Aucun rôle n\'est attribué à votre compte. Contactez un administrateur.']);
        }

        $sessionRoleId = $request->session()->get(self::SESSION_KEY);

        if ($count === 1) {
            // Auto-pin the only role we have, idempotent.
            $only = (string) $user->roles->first()->id;
            if ($sessionRoleId !== $only) {
                $request->session()->put(self::SESSION_KEY, $only);
            }
            return $next($request);
        }

        // Multi-role from here on.
        if (! is_string($sessionRoleId) || $sessionRoleId === '') {
            return redirect()->route('role.picker');
        }

        // The pinned role must still belong to the user (admins might have
        // revoked it between the picker and now). If it doesn't, drop the
        // session key and re-prompt.
        if (! $user->roles->contains('id', $sessionRoleId)) {
            $request->session()->forget(self::SESSION_KEY);
            return redirect()->route('role.picker');
        }

        return $next($request);
    }
}
