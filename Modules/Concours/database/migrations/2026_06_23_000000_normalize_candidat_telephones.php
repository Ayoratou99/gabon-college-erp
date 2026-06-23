<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * One-off clean-up: candidat phone numbers used to be stored with whatever
 * separators the user typed ("066-22-88-77"). They're now normalised to
 * digits-only at validation time; this back-fills existing rows so display,
 * dedup and lookups stay consistent.
 *
 * Two safety guards:
 *   1. Only rows that already LOOK like a phone (optional leading +, then
 *      digits/space/dot/hyphen) AND contain a separator are touched — legacy
 *      "email-in-tel" garbage (with @ / letters) never matches and is left
 *      alone.
 *   2. A row is skipped when stripping its separators would collide with
 *      another row in the same session (NOT EXISTS guard) — so this can never
 *      violate the per-session telephone unique index. Any such colliders are
 *      left formatted for manual review (expected to be none / very few).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return; // separators-strip uses Postgres regexp_replace
        }

        DB::statement(<<<'SQL'
            UPDATE candidats c
            SET telephone = regexp_replace(c.telephone, '[- .]', '', 'g')
            WHERE c.telephone ~ '^[+0-9][-0-9 .]{5,29}$'
              AND c.telephone ~ '[- .]'
              AND NOT EXISTS (
                  SELECT 1
                  FROM candidats o
                  WHERE o.id <> c.id
                    AND o.concours_session_id = c.concours_session_id
                    AND o.deleted_at IS NULL
                    AND regexp_replace(o.telephone, '[^0-9]', '', 'g')
                        = regexp_replace(c.telephone, '[^0-9]', '', 'g')
              )
        SQL);
    }

    public function down(): void
    {
        // Irreversible: the original separators aren't recoverable and the
        // normalised value is itself valid, so rolling back is a no-op.
    }
};
