<?php

declare(strict_types=1);

namespace Modules\UserManagement\Http\Controllers\Auth;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Modules\UserManagement\Models\User;
use Modules\UserManagement\Services\AuthenticationService;
use Modules\UserManagement\Services\TwoFactorService;

/**
 * Activation flow for a candidat-promoted-to-étudiant account:
 *
 *   GET  /connexion/premiere-fois              → identify form (email + tel)
 *   POST /connexion/premiere-fois              → validate, stash pre-auth user id
 *   GET  /connexion/premiere-fois/mot-de-passe → set password form
 *   POST /connexion/premiere-fois/mot-de-passe → save bcrypt hash, clear flag
 *   GET  /connexion/premiere-fois/2fa          → QR + OTP enrolment
 *   POST /connexion/premiere-fois/2fa          → verify OTP, log them in
 *
 * Stateful via the session — same `2fa.pre_auth_user_id` key as the regular
 * flow so the existing `EnsureTwoFactorVerified` hooks behave consistently
 * once they continue through normal login next time.
 */
final class FirstLoginController extends Controller
{
    private const SESSION_KEY = 'first_login.user_id';

    public function __construct(
        private readonly TwoFactorService $twoFactor,
        private readonly AuthenticationService $auth,
    ) {}

    // ---------------------------------------------------- Step 1: identify

    public function showIdentifyForm(): View
    {
        return view('usermanagement::auth.first-login.identify');
    }

    public function submitIdentify(Request $request): RedirectResponse
    {
        $data = Validator::validate($request->all(), [
            'email'     => ['required', 'email:rfc'],
            'telephone' => ['required', 'string', 'max:30'],
        ]);

        $email = mb_strtolower(trim($data['email']));
        $tel   = preg_replace('/\s+/', '', trim($data['telephone'])) ?? '';

        $user = User::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->where('telephone', $tel)
            ->first();

        // Same vague error for "no match" and "already activated" — avoids
        // leaking which accounts have been promoted.
        if ($user === null || ! $user->needsActivation()) {
            return back()->withInput()->withErrors([
                'email' => 'Aucun compte étudiant n\'est en attente d\'activation pour ces informations. Vérifiez votre email et votre téléphone, ou contactez le support.',
            ]);
        }

        $request->session()->put(self::SESSION_KEY, $user->getKey());
        return redirect()->route('first-login.password.form');
    }

    // ---------------------------------------------------- Step 2: password

    public function showPasswordForm(Request $request): RedirectResponse|View
    {
        $user = $this->stashedUserOrRedirect($request);
        if (! $user instanceof User) {
            return $user;
        }
        return view('usermanagement::auth.first-login.password', ['user' => $user]);
    }

    public function submitPassword(Request $request): RedirectResponse
    {
        $user = $this->stashedUserOrRedirect($request);
        if (! $user instanceof User) {
            return $user;
        }

        $data = Validator::validate($request->all(), [
            'password'              => ['required', 'string', 'min:10', 'confirmed'],
            'password_confirmation' => ['required', 'string'],
        ], [
            'password.min'       => 'Choisissez un mot de passe d\'au moins 10 caractères.',
            'password.confirmed' => 'Les deux mots de passe ne correspondent pas.',
        ]);

        $user->forceFill([
            'password'          => Hash::make($data['password']),
            'must_set_password' => false,
            'password_legacy'   => false,
        ])->save();

        return redirect()->route('first-login.2fa.form');
    }

    // ---------------------------------------------------- Step 3: 2FA enrol

    public function showTwoFactorForm(Request $request): RedirectResponse|View
    {
        $user = $this->stashedUserOrRedirect($request);
        if (! $user instanceof User) {
            return $user;
        }

        // Skip 2FA enrolment if the user's role doesn't require it (e.g. plain student).
        if (! $this->twoFactor->isRequiredFor($user)) {
            return $this->finalize($request, $user);
        }

        // Reuse the secret across reloads — regenerating on every GET would
        // invalidate the QR code the user scanned earlier and produce the
        // confusing "Code invalide" error after a page refresh.
        $secretKey = config('usermanagement.two_factor.session_keys.enrolling_secret');
        $secret    = (string) $request->session()->get($secretKey, '');
        if ($secret === '') {
            $enrollment = $this->twoFactor->startEnrollment($user);
            $secret = $enrollment['secret'];
            $request->session()->put($secretKey, $secret);
        }
        // Always recompute the QR from the cached secret so the user sees
        // the right code even if they reload.
        $enrollment = $this->twoFactor->renderForExistingSecret($user, $secret);

        return view('usermanagement::auth.first-login.two-factor', [
            'user'   => $user,
            'qr'     => $enrollment['qr_svg'],
            'secret' => $secret,
        ]);
    }

    public function submitTwoFactor(Request $request): RedirectResponse
    {
        $user = $this->stashedUserOrRedirect($request);
        if (! $user instanceof User) {
            return $user;
        }

        $data = Validator::validate($request->all(), [
            'otp' => ['required', 'digits:6'],
        ]);

        $secret = (string) $request->session()->get(
            config('usermanagement.two_factor.session_keys.enrolling_secret'),
        );

        if ($secret === '' || ! $this->twoFactor->confirmEnrollment($user, $secret, $data['otp'])) {
            return back()->withErrors(['otp' => 'Code invalide. Vérifiez l\'heure de votre téléphone et réessayez.']);
        }

        return $this->finalize($request, $user);
    }

    // ---------------------------------------------------- helpers

    private function stashedUserOrRedirect(Request $request): User|RedirectResponse
    {
        $userId = $request->session()->get(self::SESSION_KEY);
        $user = $userId !== null ? User::query()->find($userId) : null;
        if (! $user instanceof User) {
            return redirect()->route('first-login.start');
        }
        return $user;
    }

    private function finalize(Request $request, User $user): RedirectResponse
    {
        $request->session()->forget([
            self::SESSION_KEY,
            config('usermanagement.two_factor.session_keys.enrolling_secret'),
        ]);

        $this->auth->loginVerifiedUser($user);
        $request->session()->regenerate();
        $request->session()->put(
            config('usermanagement.two_factor.session_keys.verified'),
            true,
        );

        // Per-role landing — étudiants go to their space, everyone else to
        // the back-office dashboard. The EnsureActiveRole middleware
        // auto-pins the role for single-role users (which freshly-activated
        // étudiants are).
        $user->loadMissing('roles');
        $landing = match ($user->roles->first()?->code) {
            'etudiant' => route('etudiant.space'),
            default    => route('dashboard'),
        };

        return redirect()->intended($landing)->with(
            'status',
            'Bienvenue ! Votre compte est activé.',
        );
    }
}
