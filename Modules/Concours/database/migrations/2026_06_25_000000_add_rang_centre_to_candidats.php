<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rank of a candidat WITHIN their exam centre, alongside the existing `rang`
 * (which ranks across the whole section, all centres combined).
 *
 * A concours is a competition, so both readings matter: the section ranking
 * decides the overall winners, while the per-centre ranking is what a centre
 * publishes locally and what candidates compare themselves against.
 *
 * Recomputed by MoyenneCalculatorService together with `rang`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('candidats', function (Blueprint $table): void {
            $table->unsignedInteger('rang_centre')->nullable()->after('rang');
        });
    }

    public function down(): void
    {
        Schema::table('candidats', function (Blueprint $table): void {
            $table->dropColumn('rang_centre');
        });
    }
};
