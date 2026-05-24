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
        Schema::create('candidats', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('concours_session_id');
            $table->uuid('centre_id');
            $table->uuid('user_id')->nullable(); // set after admission → User conversion (Stage 5B)

            // Identity (split email/phone — both required + unique per session)
            $table->string('nom', 100);
            $table->string('prenom', 100);
            $table->date('date_naissance');
            $table->string('lieu_naissance', 100);
            $table->char('sexe', 1);            // M | F
            $table->uuid('nationalite_id');
            $table->string('email', 191);
            $table->string('telephone', 30);

            // Bac
            $table->boolean('deja_bac');                  // a-t-il déjà le BAC ?
            $table->unsignedSmallInteger('annee_bac')->nullable(); // requis si deja_bac = true
            $table->uuid('serie_bac_id');
            $table->string('bac_libelle_libre', 191)->nullable(); // si série = "Autre"
            $table->string('etablissement_frequente', 191);

            // Choix de formation
            $table->uuid('section_premier_choix_id');
            $table->uuid('section_second_choix_id')->nullable();

            // Pieces visuelles
            $table->string('photo_path', 500)->nullable();
            $table->string('photo_disk', 50)->nullable();

            // Statut & public id
            $table->string('statut', 20)->default('non');
            $table->string('matricule_public', 16)->unique(); // short, human-readable for verification
            $table->unsignedInteger('rang')->nullable();      // post-results
            $table->decimal('moyenne', 5, 2)->nullable();     // post-results
            $table->timestamp('valide_at')->nullable();
            $table->timestamp('rejete_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Hot queries
            $table->index(['concours_session_id', 'statut']);
            $table->index(['concours_session_id', 'centre_id', 'statut']);
            $table->index(['concours_session_id', 'section_premier_choix_id', 'statut']);

            // FKs
            $table->foreign('concours_session_id')->references('id')->on('concours_sessions')->restrictOnDelete();
            $table->foreign('centre_id')->references('id')->on('centres')->restrictOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('nationalite_id')->references('id')->on('nationalites')->restrictOnDelete();
            $table->foreign('serie_bac_id')->references('id')->on('series_bac')->restrictOnDelete();
            $table->foreign('section_premier_choix_id')->references('id')->on('sections')->restrictOnDelete();
            $table->foreign('section_second_choix_id')->references('id')->on('sections')->nullOnDelete();
        });

        // Unique email + telephone per session, partial (case-insensitive email).
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX candidats_email_per_session
            ON candidats (concours_session_id, LOWER(email))
            WHERE deleted_at IS NULL
        SQL);

        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX candidats_telephone_per_session
            ON candidats (concours_session_id, telephone)
            WHERE deleted_at IS NULL
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('candidats');
    }
};
