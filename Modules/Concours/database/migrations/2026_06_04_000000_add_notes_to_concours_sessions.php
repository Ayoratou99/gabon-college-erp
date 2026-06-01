<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Two free-text notes per session:
 *   - notes_importantes : shown at the header of the LAST step (documents) of
 *                         the inscription / modification wizard.
 *   - planning_note     : shown at the BOTTOM of the candidate's emploi du
 *                         temps PDF; edited from the planning board.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('concours_sessions', function (Blueprint $table): void {
            $table->text('notes_importantes')->nullable()->after('nombre_choix');
            $table->text('planning_note')->nullable()->after('notes_importantes');
        });
    }

    public function down(): void
    {
        Schema::table('concours_sessions', function (Blueprint $table): void {
            $table->dropColumn(['notes_importantes', 'planning_note']);
        });
    }
};
