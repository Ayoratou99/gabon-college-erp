<?php

declare(strict_types=1);

namespace Modules\Concours\Services;

use Illuminate\Cache\RateLimiter;
use Illuminate\Support\Collection;
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
     * Multi-criteria public lookup, scoped to the active session.
     *
     * The single-input search box accepts a matricule, an email substring,
     * a phone number, or a name fragment. If the term looks like a matricule
     * we short-circuit to byMatricule(). Otherwise we OR across name/email
     * /telephone columns. Capped at `$maxResults` rows so the disambiguation
     * UI stays manageable; the caller checks the count to decide whether
     * to render one candidat or a list.
     *
     * @return Collection<int, Candidat>
     */
    public function searchActiveSession(string $term, int $maxResults = 8): Collection
    {
        $term = trim($term);
        if ($term === '') {
            return collect();
        }

        // Matricule fast-path: any input that contains "CUK-" is treated as a
        // matricule attempt, even with surrounding whitespace.
        $upper = mb_strtoupper($term);
        if (str_contains($upper, 'CUK-')) {
            $hit = $this->byMatricule($upper);
            return $hit ? collect([$hit]) : collect();
        }

        $session = ConcoursSession::publicCurrent();
        if ($session === null) {
            return collect();
        }

        $needle    = mb_strtolower($term);
        $likeTerm  = '%' . $needle . '%';
        $phoneOnly = preg_replace('/\D+/', '', $term) ?? '';

        return Candidat::query()
            ->where('concours_session_id', $session->getKey())
            ->where(function ($q) use ($likeTerm, $phoneOnly): void {
                $q->whereRaw('LOWER(nom) LIKE ?',    [$likeTerm])
                  ->orWhereRaw('LOWER(prenom) LIKE ?', [$likeTerm])
                  ->orWhereRaw('LOWER(email) LIKE ?',  [$likeTerm])
                  ->orWhereRaw("LOWER(nom || ' ' || prenom) LIKE ?", [$likeTerm])
                  ->orWhereRaw("LOWER(prenom || ' ' || nom) LIKE ?", [$likeTerm]);
                if ($phoneOnly !== '' && strlen($phoneOnly) >= 5) {
                    $q->orWhere('telephone', 'like', '%' . $phoneOnly . '%');
                }
            })
            ->with(['centre:id,nom', 'premierChoix:id,nom,code'])
            // Most-recent first so an admin / candidat with a fresh
            // re-submission sees their new dossier at the top.
            ->orderByDesc('created_at')->orderBy('nom')->orderBy('prenom')
            ->limit($maxResults + 1) // +1 so we can tell "more than max"
            ->get();
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

        $session = ConcoursSession::publicCurrent();
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
