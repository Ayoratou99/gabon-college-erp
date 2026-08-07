<?php

declare(strict_types=1);

namespace Modules\Concours\Services;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Modules\Concours\Models\Candidat;
use Modules\Concours\Models\Centre;

/**
 * Allocates the "identifiant secret" used for anonymous grading.
 *
 * Format: {CENTRE}-{NNNNNN} — e.g. LBV-000001, MOU-000001.
 *
 * The prefix is the candidat's exam centre (Centre::secretPrefix(), 3 upper
 * case letters) and the sequence restarts at 000001 **within each centre of
 * each session**, so a corrector working on one centre reads short, ordered
 * numbers while the full code stays unique across the session.
 *
 * Concurrency: two simultaneous registrations can compute the same number.
 * That race is settled by the partial unique index on (concours_session_id,
 * identifiant_secret) — we simply retry with the next free number rather than
 * serialising every inscription behind a lock.
 */
final class SecretIdentifierGenerator
{
    private const WIDTH    = 6;
    private const ATTEMPTS = 5;

    /**
     * Assign an identifier to a candidat that doesn't have one yet.
     * Idempotent: returns the existing value untouched when already set.
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

        $prefix = $this->prefixFor($candidat);

        for ($attempt = 0; $attempt < self::ATTEMPTS; $attempt++) {
            $next = $this->nextFor($sessionId, $prefix, $attempt);
            try {
                $candidat->forceFill(['identifiant_secret' => $next])->save();

                return $next;
            } catch (QueryException $e) {
                if (! $this->isUniqueViolation($e)) {
                    throw $e;
                }
                // Someone took this number between our SELECT and UPDATE —
                // recompute and try the next one.
            }
        }

        return null;
    }

    /** UPPERCASE centre prefix for this candidat (falls back to XXX). */
    public function prefixFor(Candidat $candidat): string
    {
        $centre = $candidat->relationLoaded('centre')
            ? $candidat->centre
            : Centre::query()->find($candidat->centre_id);

        return $centre?->secretPrefix() ?? 'XXX';
    }

    /**
     * Next free number for a (session, centre-prefix) pair — the sequence
     * restarts at 000001 for every centre.
     */
    public function nextFor(string $sessionId, string $prefix, int $offset = 0): string
    {
        $max = (int) DB::table('candidats')
            ->where('concours_session_id', $sessionId)
            ->whereNull('deleted_at')
            ->where('identifiant_secret', 'like', $prefix . '-%')
            // Only well-formed values take part in the sequence, so a
            // hand-edited entry can never break the cast.
            ->where('identifiant_secret', '~', '^' . preg_quote($prefix, '/') . '-[0-9]+$')
            ->selectRaw("COALESCE(MAX(split_part(identifiant_secret, '-', 2)::bigint), 0) AS m")
            ->value('m');

        return $prefix . '-' . str_pad((string) ($max + 1 + $offset), self::WIDTH, '0', STR_PAD_LEFT);
    }

    private function isUniqueViolation(QueryException $e): bool
    {
        return ($e->errorInfo[0] ?? null) === '23505'   // Postgres unique_violation
            || str_contains(mb_strtolower($e->getMessage()), 'unique');
    }
}
