<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tracker columns the cutover script populates. Lets us:
     *   - Detect already-imported rows on re-runs (idempotency).
     *   - Trace any post-migration data issue back to the source MariaDB row.
     *
     * UNIQUE constraint prevents accidental double-imports.
     */
    public function up(): void
    {
        Schema::table('candidats', function (Blueprint $table): void {
            $table->unsignedInteger('legacy_id')->nullable()->after('id');
            $table->unique('legacy_id', 'candidats_legacy_id_unique');
        });

        Schema::table('payments', function (Blueprint $table): void {
            $table->unsignedInteger('legacy_id')->nullable()->after('id');
            $table->unique('legacy_id', 'payments_legacy_id_unique');
        });

        Schema::table('concours_sessions', function (Blueprint $table): void {
            $table->unsignedInteger('legacy_id')->nullable()->after('id');
            $table->unique('legacy_id', 'concours_sessions_legacy_id_unique');
        });
    }

    public function down(): void
    {
        Schema::table('candidats',         fn (Blueprint $t) => $t->dropUnique('candidats_legacy_id_unique')->dropColumn('legacy_id'));
        Schema::table('payments',          fn (Blueprint $t) => $t->dropUnique('payments_legacy_id_unique')->dropColumn('legacy_id'));
        Schema::table('concours_sessions', fn (Blueprint $t) => $t->dropUnique('concours_sessions_legacy_id_unique')->dropColumn('legacy_id'));
    }
};
