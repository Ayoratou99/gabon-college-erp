<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Schedule of an epreuve at a specific centre (per session). The same
     * epreuve can run at different times across centres — that's the whole
     * point of decoupling from the legacy single-row-per-year design.
     *
     * Salle is optional (small centres may not assign a specific room
     * pre-day-of-exam). The PlanningService can flag conflicts (same salle,
     * overlapping times).
     */
    public function up(): void
    {
        Schema::create('epreuve_plannings', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('epreuve_id');
            $table->uuid('concours_session_centre_id'); // FK to the session-centre pivot
            $table->uuid('salle_id')->nullable();

            $table->date('date_epreuve');
            $table->time('heure_debut');
            $table->time('heure_fin');

            $table->text('consigne')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['epreuve_id', 'concours_session_centre_id'], 'epreuve_centre_unique');
            $table->index(['date_epreuve', 'heure_debut']);
            $table->index(['salle_id', 'date_epreuve']);
            $table->index('concours_session_centre_id');

            $table->foreign('epreuve_id')->references('id')->on('epreuves')->cascadeOnDelete();
            $table->foreign('concours_session_centre_id')->references('id')->on('concours_session_centres')->cascadeOnDelete();
            $table->foreign('salle_id')->references('id')->on('salles')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('epreuve_plannings');
    }
};
