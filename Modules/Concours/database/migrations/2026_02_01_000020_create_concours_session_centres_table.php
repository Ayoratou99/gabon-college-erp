<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Which centres are active for which session, with optional per-session
     * overrides for capacity and a `lieu_concours` (the actual venue address
     * for that session — replaces the awkward `centres.lieuconcour` column
     * the legacy app overwrote every year).
     */
    public function up(): void
    {
        Schema::create('concours_session_centres', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('concours_session_id');
            $table->uuid('centre_id');
            $table->string('lieu_concours', 255)->nullable();   // actual venue this session
            $table->unsignedInteger('capacite_override')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['concours_session_id', 'centre_id']);
            $table->index(['concours_session_id', 'active']);

            $table->foreign('concours_session_id')->references('id')->on('concours_sessions')->cascadeOnDelete();
            $table->foreign('centre_id')->references('id')->on('centres')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('concours_session_centres');
    }
};
