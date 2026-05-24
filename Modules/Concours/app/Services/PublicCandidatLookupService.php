<?php

declare(strict_types=1);

namespace Modules\Concours\Services;

use Illuminate\Cache\RateLimiter;
use Modules\Concours\Exceptions\CandidatNotFoundException;
use Modules\Concours\Models\Candidat;
use Modules\Concours\Models\ConcoursSession;

/**
 * Two-flavour public lookup:
 *
 *   1. byMatricule()    : status check after registration (low risk).
 *   2. byEmailAndPhone(): identity-asserting lookup that grants a
 *                          modification token for a rejected dossier.
 *
 * The email+phone flow is throttled in Redis to limit credential probing.
 */
final class PublicCandidatLookupService
{
    public function __construct(
        private readonly RateLimiter $limiter,
    ) {}

    public function byMatricule(string $matricule): ?Candidat
    {
        if ($matricule === '') {
            return null;
        }
        return Candidat::query()
            ->where('matricule_public', mb_strtoupper(trim($matricule)))
            ->first();
    }

    /**
     * Locate the candidate by (session, email, telephone). Throttled per
     * (email, IP) to slow down enumeration. Returns null on miss — the
     * controller decides whether to leak that.
     *
     * @throws \Modules\UserManagement\Exceptions\LoginThrottledException
     */
    public function byEmailAndPhone(string $email, string $telephone, string $ipAddress): ?Candidat
    {
        $email = mb_strtolower(trim($email));
        $telephone = preg_replace('/\s+/', '', trim($telephone)) ?? $telephone;

        $config = (array) config('concours.public_lookup.throttle');
        $max    = (int) ($config['max_attempts']  ?? 5);
        $decay  = (int) ($config['decay_seconds'] ?? 900);
        $key    = "lookup:{$email}:{$ipAddress}";

        if ($this->limiter->tooManyAttempts($key, $max)) {
            throw new \Modules\UserManagement\Exceptions\LoginThrottledException(
                retryAfterSeconds: $this->limiter->availableIn($key),
                tier: 'lookup',
            );
        }

        $session = ConcoursSession::active();
        if ($session === null) {
            $this->limiter->hit($key, $decay);
            return null;
        }

        $candidat = Candidat::query()
            ->where('concours_session_id', $session->getKey())
            ->whereRaw('LOWER(email) = ?', [$email])
            ->where('telephone', $telephone)
            ->first();

        if ($candidat === null) {
            $this->limiter->hit($key, $decay);
        } else {
            $this->limiter->clear($key);
        }

        return $candidat;
    }

    public function findOrFail(string $matricule): Candidat
    {
        return $this->byMatricule($matricule) ?? throw CandidatNotFoundException::forLookup();
    }
}
