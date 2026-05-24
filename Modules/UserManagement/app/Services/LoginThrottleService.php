<?php

declare(strict_types=1);

namespace Modules\UserManagement\Services;

use Illuminate\Cache\RateLimiter;
use Modules\UserManagement\Exceptions\LoginThrottledException;

/**
 * Two-tier login throttle.
 *
 *   tier "fast" : 3 attempts / 15 min   → short circuit a focused brute-force
 *   tier "slow" : 5 attempts / 24 h     → catches persistent attackers, triggers alert
 *
 * Both tiers share the same throttle key (identifier + IP) and are checked
 * *before* the password is verified. Only failed attempts hit the counters;
 * a successful login clears both.
 */
final class LoginThrottleService
{
    public function __construct(
        private readonly RateLimiter $limiter,
    ) {}

    /**
     * @throws LoginThrottledException when either tier is exhausted
     */
    public function assertNotThrottled(string $identifier, string $ipAddress): void
    {
        foreach (['fast', 'slow'] as $tier) {
            $key = $this->key($tier, $identifier, $ipAddress);
            $max = $this->maxFor($tier);

            if ($this->limiter->tooManyAttempts($key, $max)) {
                throw new LoginThrottledException(
                    retryAfterSeconds: $this->limiter->availableIn($key),
                    tier: $tier,
                );
            }
        }
    }

    /**
     * Records a failure on both tiers. Returns the list of tiers that *just*
     * crossed their lockout threshold (so the caller can react — e.g. send
     * an AccountLockedNotification when the slow tier triggers).
     *
     * @return list<string>
     */
    public function recordFailure(string $identifier, string $ipAddress): array
    {
        $newlyLocked = [];
        foreach (['fast', 'slow'] as $tier) {
            $key = $this->key($tier, $identifier, $ipAddress);
            $max = $this->maxFor($tier);
            $beforeAttempts = $this->limiter->attempts($key);
            $this->limiter->hit($key, $this->decayFor($tier));
            $afterAttempts = $beforeAttempts + 1;

            if ($beforeAttempts < $max && $afterAttempts >= $max) {
                $newlyLocked[] = $tier;
            }
        }
        return $newlyLocked;
    }

    public function clear(string $identifier, string $ipAddress): void
    {
        foreach (['fast', 'slow'] as $tier) {
            $this->limiter->clear($this->key($tier, $identifier, $ipAddress));
        }
    }

    public function remainingAttempts(string $identifier, string $ipAddress): int
    {
        // Lowest remaining across tiers is what the user actually has.
        $remaining = [];
        foreach (['fast', 'slow'] as $tier) {
            $remaining[] = $this->limiter->remaining(
                $this->key($tier, $identifier, $ipAddress),
                $this->maxFor($tier),
            );
        }
        return max(0, min($remaining));
    }

    private function key(string $tier, string $identifier, string $ipAddress): string
    {
        return sprintf('login:%s:%s:%s', $tier, mb_strtolower($identifier), $ipAddress);
    }

    private function maxFor(string $tier): int
    {
        return (int) config("usermanagement.throttle.{$tier}.max_attempts");
    }

    private function decayFor(string $tier): int
    {
        return (int) config("usermanagement.throttle.{$tier}.decay_seconds");
    }
}
