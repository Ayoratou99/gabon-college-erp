<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Extend epreuve_plannings so the emploi du temps can hold:
 *   - NON-ÉPREUVE lines (`epreuve_id` nullable + `libelle_libre` + `kind`),
 *     e.g. « Pause déjeuner », « Accueil des candidats »,
 *   - a manual `ordre` so the drag-and-drop board can persist row order.
 *
 * No room/salle is recorded on the timetable: with many centres, assigning a
 * room per centre is impractical, so the emploi du temps is room-less. The
 * legacy `salle_id` column is left in place but unused.
 *
 * `epreuve_id` becomes nullable. The existing UNIQUE(epreuve_id,
 * concours_session_centre_id) keeps real épreuves to one slot per centre while
 * letting any number of break lines coexist (Postgres treats NULLs as distinct
 * in a unique index).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('epreuve_plannings', function (Blueprint $table): void {
            $table->string('kind', 20)->default('epreuve')->after('epreuve_id'); // epreuve | pause | autre
            $table->string('libelle_libre', 191)->nullable()->after('salle_id'); // label for non-épreuve lines
            $table->unsignedSmallInteger('ordre')->default(0)->after('consigne');
        });

        // Non-épreuve lines (pause, etc.) have no epreuve_id.
        DB::statement('ALTER TABLE epreuve_plannings ALTER COLUMN epreuve_id DROP NOT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE epreuve_plannings ALTER COLUMN epreuve_id SET NOT NULL');

        Schema::table('epreuve_plannings', function (Blueprint $table): void {
            $table->dropColumn(['kind', 'libelle_libre', 'ordre']);
        });
    }
};
