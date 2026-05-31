<?php

declare(strict_types=1);

namespace Modules\UserManagement\Services;

use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Carbon\Carbon;
use PragmaRX\Google2FA\Google2FA;
use Modules\UserManagement\Models\User;

/**
 * Wraps PragmaRX\Google2FA with helpers tailored to our enrolment flow.
 *
 *   1. startEnrollment($user)        → returns secret + provisioning URI + SVG QR
 *   2. confirmEnrollment($user, otp) → user types the 6-digit code; we persist
 *                                       the secret and stamp confirmed_at
 *   3. verify($user, otp)            → routine challenge after login
 *
 * Secrets are stored encrypted-at-rest via the `encrypted` cast on the
 * User model, so what's written here is plaintext to the model but
 * ciphertext to Postgres.
 */
final class TwoFactorService
{
    public function __construct(
        private readonly Google2FA $engine,
    ) {}

    /**
     * Begin enrolment. The secret is *not* persisted yet — the caller stashes
     * it in the session so the user must confirm with a valid OTP before we
     * commit it. This prevents leaving half-configured accounts behind.
     *
     * @return array{secret: string, uri: string, qr_svg: string}
     */
    public function startEnrollment(User $user): array
    {
        $secret = $this->engine->generateSecretKey();
        return $this->renderForExistingSecret($user, $secret);
    }

    /**
     * Re-render the provisioning URI + QR for an already-allocated secret.
     * Used when a view is re-displayed (e.g. user reloaded /2fa) so we
     * don't rotate the secret and invalidate the QR they already scanned.
     *
     * @return array{secret: string, uri: string, qr_svg: string}
     */
    public function renderForExistingSecret(User $user, string $secret): array
    {
        $issuer  = (string) config('usermanagement.two_factor.issuer');
        $account = $user->email ?: $user->telephone ?: $user->getKey();
        $uri     = $this->engine->getQRCodeUrl($issuer, $account, $secret);

        return [
            'secret' => $secret,
            'uri'    => $uri,
            'qr_svg' => $this->renderQrSvg($uri),
        ];
    }

    /**
     * Confirm enrolment by verifying the user's first OTP against the
     * candidate secret held in the session, then persist it.
     */
    public function confirmEnrollment(User $user, string $candidateSecret, string $otp): bool
    {
        if (! $this->engine->verifyKey($candidateSecret, $otp, (int) config('usermanagement.two_factor.window'))) {
            return false;
        }

        $user->forceFill([
            'google2fa_secret'       => $candidateSecret,
            'google2fa_confirmed_at' => Carbon::now(),
        ])->save();

        return true;
    }

    public function verify(User $user, string $otp): bool
    {
        if ($user->google2fa_secret === null) {
            return false;
        }
        return $this->engine->verifyKey(
            $user->google2fa_secret,
            $otp,
            (int) config('usermanagement.two_factor.window'),
        );
    }

    public function isRequiredFor(User $user): bool
    {
        if (! config('usermanagement.two_factor.enabled', true)) {
            return false;
        }
        $forceFor = (array) config('usermanagement.two_factor.force_for_roles', []);
        if ($forceFor === []) {
            return true; // force for everyone if no allow-list configured
        }
        return $user->hasAnyRole(...$forceFor);
    }

    private function renderQrSvg(string $uri): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle(220),
            new SvgImageBackEnd(),
        );
        return (new Writer($renderer))->writeString($uri);
    }
}
