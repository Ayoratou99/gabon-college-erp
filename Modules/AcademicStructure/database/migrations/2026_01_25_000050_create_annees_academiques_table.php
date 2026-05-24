<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('annees_academiques', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('code', 20)->unique();              // "2025-2026"
            $table->date('date_debut');
            $table->date('date_fin');
            $table->string('statut', 20)->default('a_venir');  // a_venir | en_cours | terminee
            $table->boolean('est_courante')->default(false);    // exactly one row should be true
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['statut', 'date_debut']);
            $table->index(['active' => false, 'display_order']);
            $table->index('date_debut');
        });

        // Postgres partial unique: exactly one "courante" année at any time.
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX annees_academiques_courante_unique
            ON annees_academiques ((est_courante))
            WHERE est_courante = TRUE AND deleted_at IS NULL
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('annees_academiques');
    }
};
