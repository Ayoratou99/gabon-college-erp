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
        Schema::create('concours_sessions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('annee_academique_id');
            $table->string('code', 30)->unique();                 // "CONCOURS-2025"
            $table->string('libelle', 191);
            $table->date('date_ouverture_inscriptions');
            $table->date('date_fermeture_inscriptions');
            $table->date('date_concours');                        // épreuve day
            $table->unsignedInteger('frais_inscription_override')->nullable(); // null => use Parametrage default
            $table->string('statut', 30)->default('a_venir');     // a_venir | inscriptions_ouvertes | inscriptions_fermees | clos
            $table->boolean('est_active')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['statut', 'date_ouverture_inscriptions']);
            $table->index('date_concours');

            $table->foreign('annee_academique_id')->references('id')->on('annees_academiques')->restrictOnDelete();
        });

        // Exactly one active concours session at a time.
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX concours_sessions_active_unique
            ON concours_sessions ((est_active))
            WHERE est_active = TRUE AND deleted_at IS NULL
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('concours_sessions');
    }
};
