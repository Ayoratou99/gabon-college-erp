<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Refines the previous "exempt all legacy rows" change into a sharper rule:
 *
 *   - EMAIL  → universally unique per session again (NO legacy exemption).
 *     An email is a personal identifier; the importer now guarantees legacy
 *     emails are unique by plus-tagging genuine collisions (+{legacyId}), so
 *     the strict index is safe again and keeps the public "mon dossier"
 *     email+tel lookup unambiguous.
 *
 *   - TELEPHONE → stays legacy-exempt. A phone is NOT a personal identifier:
 *     families share one line, and "+1" isn't a valid number, so we can't
 *     disambiguate it. Two distinct legacy people who share a phone (proven
 *     distinct by the name-or-DOB dedupe) are allowed to keep the same real
 *     number.
 *
 * Upgrade-safety: before re-creating the universal email index we plus-tag any
 * pre-existing duplicate real emails (e.g. the Mouhamed/Pierre pair imported
 * under the looser rule), mirroring exactly what the importer would now do at
 * insert time, so the index builds without error on an already-populated DB.
 * On a fresh DB the dedup step is a no-op.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1) Plus-tag existing duplicate REAL emails (skip @cuk.local placeholders).
        //    Keep the lowest legacy_id clean; tag the rest with "+{legacy_id}".
        $dupes = DB::select(<<<'SQL'
            SELECT concours_session_id, LOWER(email) AS email_lc
            FROM candidats
            WHERE deleted_at IS NULL
              AND email IS NOT NULL
              AND email NOT LIKE '%@cuk.local'
            GROUP BY concours_session_id, LOWER(email)
            HAVING COUNT(*) > 1
        SQL);

        foreach ($dupes as $d) {
            $rows = DB::table('candidats')
                ->where('concours_session_id', $d->concours_session_id)
                ->whereNull('deleted_at')
                ->whereRaw('LOWER(email) = ?', [$d->email_lc])
                ->orderBy('legacy_id')
                ->get(['id', 'legacy_id', 'email']);

            $first = true;
            foreach ($rows as $r) {
                if ($first) { $first = false; continue; } // first owner keeps it clean
                $email = (string) $r->email;
                $at = mb_strpos($email, '@');
                $tagged = $at === false
                    ? "{$email}+{$r->legacy_id}"
                    : mb_substr($email, 0, $at) . "+{$r->legacy_id}" . mb_substr($email, $at);
                DB::table('candidats')->where('id', $r->id)->update([
                    'email'      => mb_strtolower($tagged),
                    'updated_at' => now(),
                ]);
            }
        }

        // 2) Recreate the EMAIL index WITHOUT the legacy exemption (universal).
        DB::statement('DROP INDEX IF EXISTS candidats_email_per_session');
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX candidats_email_per_session
            ON candidats (concours_session_id, LOWER(email))
            WHERE deleted_at IS NULL
        SQL);

        // 3) Telephone index: ensure it remains legacy-exempt (idempotent).
        DB::statement('DROP INDEX IF EXISTS candidats_telephone_per_session');
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX candidats_telephone_per_session
            ON candidats (concours_session_id, telephone)
            WHERE deleted_at IS NULL AND legacy_id IS NULL
        SQL);
    }

    public function down(): void
    {
        // Revert to the previous migration's state (both indexes legacy-exempt).
        DB::statement('DROP INDEX IF EXISTS candidats_email_per_session');
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX candidats_email_per_session
            ON candidats (concours_session_id, LOWER(email))
            WHERE deleted_at IS NULL AND legacy_id IS NULL
        SQL);
        // telephone index already legacy-exempt — leave as is.
    }
};
