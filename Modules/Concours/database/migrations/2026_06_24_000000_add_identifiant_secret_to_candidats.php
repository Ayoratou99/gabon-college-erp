<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Anonymous grading identifier ("identifiant secret").
 *
 * A zero-padded sequence — 000001, 000002, … — assigned per concours session.
 * It exists so notes can be entered against a number instead of a name: the
 * grading grid shows ONLY this value, never the candidate's identity.
 *
 * Nullable on purpose: adding it must not break the existing rows, and the
 * legacy imports may carry candidats we never re-number. Uniqueness is scoped
 * to the session (a partial index, so NULLs and soft-deleted rows are exempt)
 * because the numbering restarts at 000001 for each new concours.
 *
 * Visibility is enforced in the application layer — only super-admin / DG / DE
 * ever see it, and it is deliberately absent from the candidat list UI.
 */
return new class extends Migration
{
    private const WIDTH = 6;

    public function up(): void
    {
        Schema::table('candidats', function (Blueprint $table): void {
            $table->string('identifiant_secret', 12)->nullable()->after('matricule_public');
        });

        // Unique per session, ignoring NULLs and soft-deleted dossiers.
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX candidats_session_identifiant_secret_unique
            ON candidats (concours_session_id, identifiant_secret)
            WHERE identifiant_secret IS NOT NULL AND deleted_at IS NULL
        SQL);

        // Back-fill every existing dossier, numbering per session in a stable
        // order (inscription date, then matricule) so re-running on a restored
        // dump reproduces the same identifiers.
        DB::statement(sprintf(<<<'SQL'
            UPDATE candidats c
            SET identifiant_secret = LPAD(seq.rn::text, %d, '0')
            FROM (
                SELECT id,
                       ROW_NUMBER() OVER (
                           PARTITION BY concours_session_id
                           ORDER BY created_at, matricule_public, id
                       ) AS rn
                FROM candidats
                WHERE deleted_at IS NULL
            ) seq
            WHERE c.id = seq.id
              AND c.deleted_at IS NULL
              AND c.identifiant_secret IS NULL
        SQL, self::WIDTH));
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS candidats_session_identifiant_secret_unique');
        Schema::table('candidats', function (Blueprint $table): void {
            $table->dropColumn('identifiant_secret');
        });
    }
};
