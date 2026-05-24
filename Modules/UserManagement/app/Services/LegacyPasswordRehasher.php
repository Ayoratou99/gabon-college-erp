<?php

declare(strict_types=1);

namespace Modules\UserManagement\Services;

use Illuminate\Contracts\Hashing\Hasher;
use Modules\UserManagement\Models\User;

/**
 * Transparently migrates SHA1 legacy passwords to bcrypt on first successful
 * login. Idempotent and constant-time-safe (`hash_equals`).
 *
 *   1. user.password_legacy = true   AND  sha1($input) === user.password
 *      → matches; we rehash and flip the flag.
 *   2. user.password_legacy = false  AND  bcrypt verify
 *      → standard flow; no change.
 *
 * If the legacy rehash is disabled in config (kill-switch for incident
 * response) we still allow the SHA1 check but skip the write.
 */
final class LegacyPasswordRehasher
{
    public function __construct(
        private readonly Hasher $hasher,
    ) {}

    /**
     * Verify the provided plaintext against the stored hash, transparently
     * upgrading SHA1 → bcrypt when applicable.
     *
     * Returns true if the password matches. Sets $rehashed by-ref so the
     * caller can emit a "rehashed" event for audit.
     */
    public function verifyAndUpgrade(User $user, string $plaintext, bool &$rehashed = false): bool
    {
        $rehashed = false;

        if ($user->isLegacyPassword()) {
            $legacyHash = $user->getAttribute('password');
            if (! hash_equals($legacyHash, sha1($plaintext))) {
                return false;
            }

            if (config('usermanagement.legacy_password_rehash', true)) {
                $user->forceFill([
                    'password'        => $this->hasher->make($plaintext),
                    'password_legacy' => false,
                ])->save();
                $rehashed = true;
            }
            return true;
        }

        if (! $this->hasher->check($plaintext, $user->getAttribute('password'))) {
            return false;
        }

        // Standard bcrypt rehash if cost factor changed (Laravel built-in).
        if ($this->hasher->needsRehash($user->getAttribute('password'))) {
            $user->forceFill(['password' => $this->hasher->make($plaintext)])->save();
        }

        return true;
    }
}
