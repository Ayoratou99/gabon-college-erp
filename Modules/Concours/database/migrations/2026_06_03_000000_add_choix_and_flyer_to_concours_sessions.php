<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-session config:
 *   - nombre_choix : how many formation choices a candidat makes (1 or 2).
 *                    1 hides/skips the « second choix » everywhere.
 *   - flyer_path / flyer_disk : optional announcement flyer (PDF or image)
 *                    surfaced as a floating « Voir l'annonce » on the home page
 *                    while inscriptions are open.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('concours_sessions', function (Blueprint $table): void {
            $table->unsignedSmallInteger('nombre_choix')->default(2)->after('frais_inscription_override');
            $table->string('flyer_path', 500)->nullable()->after('nombre_choix');
            $table->string('flyer_disk', 50)->nullable()->after('flyer_path');
        });
    }

    public function down(): void
    {
        Schema::table('concours_sessions', function (Blueprint $table): void {
            $table->dropColumn(['nombre_choix', 'flyer_path', 'flyer_disk']);
        });
    }
};
