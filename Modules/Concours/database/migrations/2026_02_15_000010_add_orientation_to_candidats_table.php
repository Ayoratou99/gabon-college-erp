<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Post-selection: which section the admis was actually orientated into.
     * May differ from first / second choice when DG/DE override the
     * suggested orientation.
     */
    public function up(): void
    {
        Schema::table('candidats', function (Blueprint $table): void {
            $table->uuid('section_orientation_id')->nullable()->after('section_second_choix_id');
            $table->timestamp('admis_at')->nullable()->after('rejete_at');

            $table->index(['concours_session_id', 'section_orientation_id', 'statut']);

            $table->foreign('section_orientation_id')->references('id')->on('sections')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('candidats', function (Blueprint $table): void {
            $table->dropForeign(['section_orientation_id']);
            $table->dropIndex(['concours_session_id', 'section_orientation_id', 'statut']);
            $table->dropColumn(['section_orientation_id', 'admis_at']);
        });
    }
};
