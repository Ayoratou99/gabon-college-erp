<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The epreuve_sections pivot is now the source of truth for which sections an
 * épreuve covers, so the legacy single-scope columns scope_type / scope_id are
 * no longer required. Make them nullable — kept only for backward-compatible
 * reads of historical rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE epreuves ALTER COLUMN scope_type DROP NOT NULL');
        DB::statement('ALTER TABLE epreuves ALTER COLUMN scope_id DROP NOT NULL');
    }

    public function down(): void
    {
        // Re-imposing NOT NULL is unsafe once null rows exist — intentional no-op.
    }
};
