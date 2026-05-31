<?php

declare(strict_types=1);

namespace Modules\UserManagement\Services;

use Carbon\Carbon;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\ConnectionInterface;
use Modules\UserManagement\DTOs\AuthenticationResultDto;
use Modules\UserManagement\DTOs\LoginCredentialsDto;
use Modules\UserManagement\Events\LoginFailed;
use Modules\UserManagement\Events\LoginSucceeded;
use Modules\UserManagement\Exceptions\AccountBlockedException;
use Modules\UserManagement\Exceptions\AccountNeedsActivationException;
use Modules\UserManagement\Exceptions\AuthenticationException;
use Modules\UserManagement\Models\LoginAttempt;
use Modules\UserManagement\Models\User;
use Modules\UserManagement\Notifications\AccountLockedNotification;

/**
 * Orchestrates the login pipeline:
 *
 *   1. throttle check (LoginThrottleService)
 *   2. user lookup by email-or-phone
 *   3. password verification (with transparent SHA1 → bcrypt upgrade)
 *   4. audit row + event + throttle update
 *   5. returns an AuthenticationResultDto telling the controller whether
 *      2FA enrolment or verification is the next stop.
 *
 * Importantly: this service does NOT open a session. It hands back the User
 * and a "requiresTwoFactor" flag; the controller decides whether to login()
 * immediately (no 2FA needed) or stash a pre-auth user_id in the session and
 * redirect to the 2FA challenge.
 */
final class AuthenticationService
{
    public function __construct(
        private readonly StatefulGuard $guard,
        private readonly LoginThrottleService $throttle,
        private readonly LegacyPasswordRehasher $rehasher,
        private readonly TwoFactorService $twoFactor,
        private readonly Dispatcher $events,
        private readonly ConnectionInterface $db,
    ) {}

    public function authenticate(LoginCredentialsDto $creds): AuthenticationResultDto
    {
        $this->throttle->assertNotThrottled($creds->identifier, $creds->ipAddress);

        $user = $this->findUser($creds->identifier);

        if ($user === null) {
            $this->recordFailure($creds, reason: 'unknown_identifier', userId: null);
            throw AuthenticationException::invalidCredentials();
        }

        // Account exists but no password ever set → owner must go through the
        // activation wizard. Don't burn a throttle slot for this branch.
        if ($user->needsActivation()) {
            throw new AccountNeedsActivationException();
        }

        // Block check — admin disabled this account. Audit the attempt as a
        // failure (so the login_attempts log shows it) but skip the throttle
        // bump: the account is locked out by design, not because someone is
        // brute-forcing it.
        if ($user->isBlocked()) {
            LoginAttempt::query()->create([
                'identifier'     => $creds->identifier,
                'ip_address'     => $creds->ipAddress,
                'user_agent'     => $creds->userAgent,
                'user_id'        => $user->getKey(),
                'succeeded'      => false,
                'failure_reason' => 'account_blocked',
                'attempted_at'   => now(),
            ]);
            throw new AccountBlockedException($user->blocked_reason);
        }

        $rehashed = false;
        if (! $this->rehasher->verifyAndUpgrade($user, $creds->password, $rehashed)) {
            $this->recordFailure($creds, reason: 'wrong_password', userId: $user->getKey());
            throw AuthenticationException::invalidCredentials();
        }

        // Success: clear throttle, audit, emit event, update last-login.
        $this->db->transaction(function () use ($user, $creds, $rehashed): void {
            $this->throttle->clear($creds->identifier, $creds->ipAddress);

            LoginAttempt::query()->create([
                'identifier'    => $creds->identifier,
                'ip_address'    => $creds->ipAddress,
                'user_agent'    => $creds->userAgent,
                'user_id'       => $user->getKey(),
                'succeeded'     => true,
                'attempted_at'  => now(),
            ]);

            $user->forceFill([
                'last_login_at' => now(),
                'last_login_ip' => $creds->ipAddress,
            ])->save();

            $this->events->dispatch(new LoginSucceeded($user, $creds->ipAddress, $creds->userAgent, $rehashed));
        });

        return new AuthenticationResultDto(
            user: $user,
            requiresTwoFactor: $this->twoFactor->isRequiredFor($user),
            requiresEnrollment: $user->needsTwoFactorEnrollment(),
            rehashedFromLegacy: $rehashed,
        );
    }

    /**
     * Called by the 2FA controller once the OTP is verified.
     */
    public function loginVerifiedUser(User $user, bool $remember = false): void
    {
        $this->guard->login($user, $remember);
    }

    public function logout(): void
    {
        $this->guard->logout();
    }

    private function findUser(string $identifier): ?User
    {
        $query = User::query()->whereNull('deleted_at');

        return str_contains($identifier, '@')
            ? $query->whereRaw('LOWER(email) = ?', [mb_strtolower($identifier)])->first()
            : $query->where('telephone', $identifier)->first();
    }

    private function recordFailure(LoginCredentialsDto $creds, string $reason, ?string $userId): void
    {
        $newlyLocked = $this->throttle->recordFailure($creds->identifier, $creds->ipAddress);

        LoginAttempt::query()->create([
            'identifier'     => $creds->identifier,
            'ip_address'     => $creds->ipAddress,
            'user_agent'     => $creds->userAgent,
            'user_id'        => $userId,
            'succeeded'      => false,
            'failure_reason' => $reason,
            'attempted_at'   => now(),
        ]);

        $this->events->dispatch(new LoginFailed(
            identifier: $creds->identifier,
            ipAddress:  $creds->ipAddress,
            userAgent:  $creds->userAgent,
            reason:     $reason,
            userId:     $userId,
        ));

        // Slow-tier lockout just triggered for a known account → notify them.
        if (in_array('slow', $newlyLocked, true) && $userId !== null) {
            $user = User::query()->find($userId);
            if ($user !== null && $user->email !== null) {
                $decay = (int) config('usermanagement.throttle.slow.decay_seconds', 86400);
                $user->notify(new AccountLockedNotification(
                    user: $user,
                    ipAddress: $creds->ipAddress,
                    unlocksAt: Carbon::now()->addSeconds($decay),
                ));
            }
        }
    }
}
