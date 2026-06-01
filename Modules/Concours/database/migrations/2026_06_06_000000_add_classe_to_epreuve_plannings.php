<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Optional free-text room/location per planning slot (e.g. « Bâtiment H, salle
 * H1 »). Set per centre when scheduling an épreuve; nullable so it never has to
 * be filled across many centres. Shown on the candidate's emploi du temps.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('epreuve_plannings', function (Blueprint $table): void {
            $table->string('classe', 191)->nullable()->after('libelle_libre');
        });
    }

    public function down(): void
    {
        Schema::table('epreuve_plannings', function (Blueprint $table): void {
            $table->dropColumn('classe');
        });
    }
};
