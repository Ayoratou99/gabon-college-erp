<?php

declare(strict_types=1);

namespace Modules\UserManagement\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\UserManagement\Models\User;
use Modules\UserManagement\Services\TwoFactorService;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates "logged-in but not 2FA-verified yet" routes.
 *
 * Authenticated routes that require a verified second factor put this
 * middleware in the stack. If 2FA is required for the role but the user
 * hasn't passed it in this session, we redirect to the challenge.
 *
 * The "pre-auth" pattern (user resolved via session but Auth::login() not
 * called yet) is intentionally NOT used by this middleware — the login
 * controller handles that branch before reaching the auth boundary.
 */
final class EnsureTwoFactorVerified
{
    public function __construct(
        private readonly TwoFactorService $twoFactor,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user === null) {
            return redirect()->route('login');
        }

        if (! $this->twoFactor->isRequiredFor($user)) {
            return $next($request);
        }

        $sessionKey = config('usermanagement.two_factor.session_keys.verified');
        if ($request->session()->get($sessionKey) === true) {
            return $next($request);
        }

        return redirect()->route('two-factor.challenge');
    }
}
