<?php

declare(strict_types=1);

namespace Modules\Concours\Services;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Modules\Concours\Models\Candidat;

/**
 * Allocates the per-session "identifiant secret" (000001, 000002, …) used for
 * anonymous grading.
 *
 * The number is derived from MAX(existing) + 1 inside the session. Two
 * concurrent registrations can therefore pick the same value — that race is
 * settled by the partial unique index on (concours_session_id,
 * identifiant_secret), and we simply retry with the next free number rather
 * than serialising every inscription behind a lock.
 */
final class SecretIdentifierGenerator
{
    private const WIDTH    = 6;
    private const ATTEMPTS = 5;

    /**
     * Assign an identifier to a candidat that doesn't have one yet.
     * Returns the value in use (existing one included — this is idempotent).
     */
    public function assign(Candidat $candidat): ?string
    {
        if ($candidat->identifiant_secret !== null && $candidat->identifiant_secret !== '') {
            return $candidat->identifiant_secret;
        }
        $sessionId = (string) $candidat->concours_session_id;
        if ($sessionId === '') {
            return null; // nothing to scope the sequence to
        }

        for ($attempt = 0; $attempt < self::ATTEMPTS; $attempt++) {
            $next = $this->nextFor($sessionId, $attempt);
            try {
                $candidat->forceFill(['identifiant_secret' => $next])->save();

                return $next;
            } catch (QueryException $e) {
                if (! $this->isUniqueViolation($e)) {
                    throw $e;
                }
                // Someone took this number between our SELECT and INSERT —
                // recompute and try the next one.
            }
        }

        return null;
    }

    /** Peek at the next free number for a session (no allocation). */
    public function nextFor(string $sessionId, int $offset = 0): string
    {
        $max = (int) DB::table('candidats')
            ->where('concours_session_id', $sessionId)
            ->whereNull('deleted_at')
            ->whereNotNull('identifiant_secret')
            // Only well-formed numeric identifiers take part in the sequence,
            // so a hand-edited value can never break the cast.
            ->where('identifiant_secret', '~', '^[0-9]+$')
            ->selectRaw('COALESCE(MAX(identifiant_secret::bigint), 0) AS m')
            ->value('m');

        return str_pad((string) ($max + 1 + $offset), self::WIDTH, '0', STR_PAD_LEFT);
    }

    private function isUniqueViolation(QueryException $e): bool
    {
        return ($e->errorInfo[0] ?? null) === '23505'   // Postgres unique_violation
            || str_contains(mb_strtolower($e->getMessage()), 'unique');
    }
}
