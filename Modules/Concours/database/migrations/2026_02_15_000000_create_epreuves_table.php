<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * An epreuve is a single subject the candidate takes on the concours day.
     *
     * Scope:
     *   - 'cycle'   : applies to every candidat whose first-choice section
     *                 belongs to scope_id (a cycles.id)
     *   - 'section' : applies only to candidats whose first-choice section_id = scope_id
     *
     * `type_epreuve_id` references the referential catalog (ecrit / oral /
     * pratique). The default duree + coefficient come from that catalog;
     * the per-epreuve columns override them.
     */
    public function up(): void
    {
        Schema::create('epreuves', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('concours_session_id');
            $table->uuid('type_epreuve_id');

            $table->string('code', 30);
            $table->string('libelle', 191);
            $table->text('description')->nullable();

            // Scope: cycle | section
            $table->string('scope_type', 20);
            $table->uuid('scope_id');

            $table->decimal('coefficient', 4, 2)->default(1.00);
            $table->unsignedSmallInteger('duree_minutes')->default(120);
            $table->decimal('note_max', 5, 2)->default(20.00);

            $table->unsignedSmallInteger('ordre')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['concours_session_id', 'code']);
            $table->index(['concours_session_id', 'scope_type', 'scope_id']);

            $table->foreign('concours_session_id')->references('id')->on('concours_sessions')->cascadeOnDelete();
            $table->foreign('type_epreuve_id')->references('id')->on('types_epreuves')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('epreuves');
    }
};
