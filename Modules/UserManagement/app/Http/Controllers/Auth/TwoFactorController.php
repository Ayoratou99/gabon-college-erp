<?php

declare(strict_types=1);

namespace Modules\UserManagement\Http\Controllers\Auth;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\UserManagement\Http\Requests\TwoFactorRequest;
use Modules\UserManagement\Models\User;
use Modules\UserManagement\Services\AuthenticationService;
use Modules\UserManagement\Services\TwoFactorService;

/**
 * Owns the post-password 2FA dance:
 *
 *   GET  /two-factor/enroll    → shows QR + secret + first-time OTP form
 *   POST /two-factor/enroll    → verifies the OTP against the candidate secret
 *                                 in session; on success persists and logs in
 *
 *   GET  /two-factor/challenge → routine 6-digit prompt
 *   POST /two-factor/challenge → verifies, marks session as verified, logs in
 *
 * Session keys (all configurable):
 *   2fa.pre_auth_user_id  → user resolved by login() but not yet authenticated
 *   2fa.enrolling_secret  → candidate secret awaiting confirmation
 *   2fa.verified          → bool, set after a successful challenge
 */
final class TwoFactorController extends Controller
{
    public function __construct(
        private readonly TwoFactorService $twoFactor,
        private readonly AuthenticationService $auth,
    ) {}

    public function showEnrollForm(Request $request): RedirectResponse|View
    {
        $user = $this->preAuthUserOrRedirect($request);
        if (! $user instanceof User) {
            return $user;
        }

        // Reuse the candidate secret across reloads so the QR the user
        // scanned stays valid when they hit refresh.
        $secretKey = config('usermanagement.two_factor.session_keys.enrolling_secret');
        $secret    = (string) $request->session()->get($secretKey, '');
        if ($secret === '') {
            $secret = $this->twoFactor->startEnrollment($user)['secret'];
            $request->session()->put($secretKey, $secret);
        }
        $enrollment = $this->twoFactor->renderForExistingSecret($user, $secret);

        return view('usermanagement::auth.two-factor', [
            'mode'   => 'enroll',
            'user'   => $user,
            'qr'     => $enrollment['qr_svg'],
            'secret' => $secret,
        ]);
    }

    public function submitEnroll(TwoFactorRequest $request): RedirectResponse
    {
        $user = $this->preAuthUserOrRedirect($request);
        if (! $user instanceof User) {
            return $user;
        }

        $secret = (string) $request->session()->get(
            config('usermanagement.two_factor.session_keys.enrolling_secret'),
        );

        if ($secret === '' || ! $this->twoFactor->confirmEnrollment($user, $secret, $request->otp())) {
            return back()->withErrors(['otp' => 'Code invalide. Vérifiez l\'heure de votre téléphone et réessayez.']);
        }

        return $this->finalize($request, $user);
    }

    public function showChallengeForm(Request $request): RedirectResponse|View
    {
        $user = $this->preAuthUserOrRedirect($request);
        if (! $user instanceof User) {
            return $user;
        }
        return view('usermanagement::auth.two-factor', [
            'mode' => 'challenge',
            'user' => $user,
        ]);
    }

    public function submitChallenge(TwoFactorRequest $request): RedirectResponse
    {
        $user = $this->preAuthUserOrRedirect($request);
        if (! $user instanceof User) {
            return $user;
        }

        if (! $this->twoFactor->verify($user, $request->otp())) {
            return back()->withErrors(['otp' => 'Code invalide.']);
        }

        return $this->finalize($request, $user);
    }

    private function preAuthUserOrRedirect(Request $request): User|RedirectResponse
    {
        $key = config('usermanagement.two_factor.session_keys.pre_auth_user_id');
        $userId = $request->session()->get($key);

        $user = $userId !== null ? User::query()->find($userId) : null;

        if (! $user instanceof User) {
            return redirect()->route('login');
        }

        return $user;
    }

    private function finalize(Request $request, User $user): RedirectResponse
    {
        $request->session()->forget([
            config('usermanagement.two_factor.session_keys.pre_auth_user_id'),
            config('usermanagement.two_factor.session_keys.enrolling_secret'),
        ]);

        $this->auth->loginVerifiedUser($user);
        $request->session()->regenerate();
        $request->session()->put(
            config('usermanagement.two_factor.session_keys.verified'),
            true,
        );

        return redirect()->intended(route('dashboard'));
    }
}
