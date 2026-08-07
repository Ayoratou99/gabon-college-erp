<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Re-issues every "identifiant secret" in the CENTRE-prefixed form:
 *
 *     000001            →   LBV-000001   (Libreville)
 *     000002            →   MOU-000001   (Mouila)
 *
 * The prefix is the 3-letter suffix of the centre's curated `code`
 * ("CENTRE-LBV" → "LBV"), uppercased, and the sequence restarts at 000001
 * inside each centre of each session — so a corrector sees short ordered
 * numbers per centre, while the code stays unique across the session.
 *
 * Candidats with no centre fall back to the "XXX" bucket rather than being
 * left without an identifier.
 */
return new class extends Migration
{
    private const WIDTH = 6;

    public function up(): void
    {
        // "LBV-000001" is 10 chars, but leave room for longer prefixes later.
        Schema::table('candidats', function (Blueprint $table): void {
            $table->string('identifiant_secret', 24)->nullable()->change();
        });

        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement(sprintf(<<<'SQL'
            WITH prefixes AS (
                SELECT c.id AS candidat_id,
                       c.concours_session_id,
                       c.created_at,
                       c.matricule_public,
                       COALESCE(
                           NULLIF(SUBSTRING(
                               REGEXP_REPLACE(UPPER(COALESCE(ce.code, '')), '^CENTRE[-_ ]*', '')
                               FROM 1 FOR 3), ''),
                           NULLIF(SUBSTRING(
                               REGEXP_REPLACE(UPPER(COALESCE(ce.nom, '')), '[^A-Z0-9]', '', 'g')
                               FROM 1 FOR 3), ''),
                           'XXX'
                       ) AS prefix
                FROM candidats c
                LEFT JOIN centres ce ON ce.id = c.centre_id
                WHERE c.deleted_at IS NULL
            ),
            numbered AS (
                SELECT candidat_id,
                       prefix,
                       ROW_NUMBER() OVER (
                           PARTITION BY concours_session_id, prefix
                           ORDER BY created_at, matricule_public, candidat_id
                       ) AS rn
                FROM prefixes
            )
            UPDATE candidats c
            SET identifiant_secret = n.prefix || '-' || LPAD(n.rn::text, %d, '0')
            FROM numbered n
            WHERE c.id = n.candidat_id
        SQL, self::WIDTH));
    }

    public function down(): void
    {
        // Strip the prefix back to the bare sequence; the column keeps its
        // widened length, which is harmless.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("UPDATE candidats SET identifiant_secret = split_part(identifiant_secret, '-', 2) WHERE identifiant_secret LIKE '%-%'");
        }
    }
};
