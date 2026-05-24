<?php

declare(strict_types=1);

namespace Modules\UserManagement\Http\Controllers\Auth;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\UserManagement\Exceptions\AuthenticationException;
use Modules\UserManagement\Exceptions\LoginThrottledException;
use Modules\UserManagement\Exceptions\RecaptchaFailedException;
use Modules\UserManagement\Http\Requests\LoginRequest;
use Modules\UserManagement\Services\AuthenticationService;

/**
 * Thin HTTP boundary. All real work is in AuthenticationService.
 *
 * Decision tree after authenticate():
 *
 *   ┌── requiresTwoFactor === false   → login the user, redirect to /home
 *   │
 *   └── requiresTwoFactor === true
 *        ├── requiresEnrollment === true → /two-factor/enroll
 *        └── requiresEnrollment === false → /two-factor/challenge
 *
 * Throttle / recaptcha / credential exceptions bubble up as flash errors.
 */
final class LoginController extends Controller
{
    public function __construct(
        private readonly AuthenticationService $auth,
    ) {}

    public function showLoginForm(): View
    {
        return view('usermanagement::auth.login');
    }

    public function login(LoginRequest $request): RedirectResponse
    {
        try {
            $result = $this->auth->authenticate($request->toDto());
        } catch (LoginThrottledException $e) {
            return back()
                ->onlyInput('identifier')
                ->withErrors(['identifier' => $e->getMessage()]);
        } catch (RecaptchaFailedException $e) {
            return back()
                ->onlyInput('identifier')
                ->withErrors(['identifier' => $e->getMessage()]);
        } catch (AuthenticationException $e) {
            return back()
                ->onlyInput('identifier')
                ->withErrors(['identifier' => $e->getMessage()]);
        }

        // No 2FA required for this role → immediate login.
        if (! $result->requiresTwoFactor) {
            $this->auth->loginVerifiedUser($result->user, $request->boolean('remember'));
            $request->session()->regenerate();
            return redirect()->intended(route('dashboard'));
        }

        // 2FA branch — stash the pre-auth user_id in session, redirect to challenge/enroll.
        $request->session()->put(
            config('usermanagement.two_factor.session_keys.pre_auth_user_id'),
            $result->user->getKey(),
        );

        return $result->requiresEnrollment
            ? redirect()->route('two-factor.enroll')
            : redirect()->route('two-factor.challenge');
    }

    public function logout(Request $request): RedirectResponse
    {
        $this->auth->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login');
    }
}
