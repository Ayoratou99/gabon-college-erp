<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Drop the `concours.fee.amount` setting.
 *
 * The registration fee is now OWNED by the concours session
 * (concours_sessions.frais_inscription_override), editable in the back-office
 * via « Sessions → Modifier ». Having the amount in BOTH the session and
 * Parametrage was ambiguous, so we remove the Parametrage copy entirely. The
 * matching `setting_change_logs` rows are removed automatically by the
 * `setting_id` foreign key (cascadeOnDelete).
 *
 * Currency (`concours.fee.currency`) and the invoice label
 * (`concours.fee.description`) stay in Parametrage — they have no per-session
 * equivalent and are therefore not ambiguous.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('settings')->where('key', 'concours.fee.amount')->delete();
    }

    public function down(): void
    {
        // No-op: re-creating the setting would reintroduce the ambiguity this
        // migration exists to remove. The fee lives on the session now.
    }
};
