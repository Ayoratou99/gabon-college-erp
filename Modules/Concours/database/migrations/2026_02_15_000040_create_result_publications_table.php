<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per session = one publication event. Re-publications would
     * append a new row (partial unique index ensures at most one ACTIVE
     * publication per session).
     */
    public function up(): void
    {
        Schema::create('result_publications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('concours_session_id');
            $table->uuid('published_by_user_id');

            $table->timestamp('published_at')->useCurrent();
            $table->unsignedInteger('total_candidats');
            $table->unsignedInteger('total_admis');
            $table->jsonb('breakdown_par_section')->nullable();   // section_id => admis count
            $table->string('fichier_path', 500)->nullable();      // optional uploaded PDF
            $table->string('fichier_disk', 50)->nullable();
            $table->text('communique')->nullable();               // optional public note

            $table->boolean('active')->default(true);             // false = superseded by a later publication
            $table->timestamps();
            $table->softDeletes();

            $table->index('concours_session_id');
            $table->index(['active', 'published_at']);

            $table->foreign('concours_session_id')->references('id')->on('concours_sessions')->cascadeOnDelete();
            $table->foreign('published_by_user_id')->references('id')->on('users')->restrictOnDelete();
        });

        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX result_publications_active_per_session
            ON result_publications (concours_session_id)
            WHERE active = TRUE AND deleted_at IS NULL
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('result_publications');
    }
};
