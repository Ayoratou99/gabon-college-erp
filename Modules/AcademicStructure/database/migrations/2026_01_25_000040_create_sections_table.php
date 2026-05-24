<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A section is a *formation* — an academic programme like "DUT Chimie
     * Industrielle". It belongs to a Cycle (DUT) and optionally a
     * Departement (Génie Chimique). Stage 5 (Concours) will reference
     * sections.id to record candidates' first + second-choice formation.
     */
    public function up(): void
    {
        Schema::create('sections', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('cycle_id');
            $table->uuid('departement_id')->nullable();
            $table->string('code', 20)->unique();
            $table->string('nom', 191);
            $table->text('description')->nullable();
            $table->unsignedInteger('places_par_session')->default(50); // default seat count per concours
            $table->boolean('ouvert_au_concours')->default(true);       // available for new admissions?
            $table->boolean('active')->default(true);
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['cycle_id', 'active']);
            $table->index(['departement_id', 'active']);
            $table->index(['ouvert_au_concours', 'active']);
            $table->index(['active', 'display_order']);

            $table->foreign('cycle_id')->references('id')->on('cycles')->restrictOnDelete();
            $table->foreign('departement_id')->references('id')->on('departements')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sections');
    }
};
