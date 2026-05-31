<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Exempt legacy-imported candidats from the per-session email/telephone
 * uniqueness indexes.
 *
 * Why: real applicants legitimately share a contact — a parent's phone, a
 * sibling's email, a cyber-café number. The legacy 2025 dump contained ≥5
 * such cases (different name AND different date of birth, same phone/email),
 * including paid candidates. The original partial unique indexes
 * (concours_session_id, telephone) / (concours_session_id, lower(email))
 * blocked importing the second person of each pair, silently dropping real
 * — sometimes paid — candidates.
 *
 * The smart dedupe in LegacyCandidatImporter (contact + name-or-DOB) is now
 * the authoritative gatekeeper for legacy rows: anything it emits is already
 * a distinct human. So the DB-level contact uniqueness is redundant for those
 * rows and only causes harm. We therefore add `legacy_id IS NULL` to both
 * partial indexes:
 *
 *   - NEW registrations (legacy_id IS NULL)  → still can't reuse a contact in
 *     the same session (guards accidental double-submit in the live wizard).
 *   - LEGACY imports     (legacy_id IS NOT NULL) → exempt; shared contacts OK.
 *
 * This is the structural change that lets future multi-session legacy dumps
 * import cleanly regardless of how many applicants share a phone.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('DROP INDEX IF EXISTS candidats_email_per_session');
        DB::statement('DROP INDEX IF EXISTS candidats_telephone_per_session');

        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX candidats_email_per_session
            ON candidats (concours_session_id, LOWER(email))
            WHERE deleted_at IS NULL AND legacy_id IS NULL
        SQL);

        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX candidats_telephone_per_session
            ON candidats (concours_session_id, telephone)
            WHERE deleted_at IS NULL AND legacy_id IS NULL
        SQL);
    }

    public function down(): void
    {
        // Restore the original (stricter) partial indexes. NB: if duplicate
        // legacy contacts are present this down() will fail — that's expected,
        // it documents that the legacy data is incompatible with the old rule.
        DB::statement('DROP INDEX IF EXISTS candidats_email_per_session');
        DB::statement('DROP INDEX IF EXISTS candidats_telephone_per_session');

        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX candidats_email_per_session
            ON candidats (concours_session_id, LOWER(email))
            WHERE deleted_at IS NULL
        SQL);

        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX candidats_telephone_per_session
            ON candidats (concours_session_id, telephone)
            WHERE deleted_at IS NULL
        SQL);
    }
};
