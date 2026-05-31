<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Free-text back-office note explaining a non-standard admission — e.g. a
 * candidat named on the 2025 procès-verbal who never registered online and was
 * added manually, or one whose existing dossier was reconciled to the PV by
 * hand. When set, the admin candidat page shows a "ajouté/rapproché
 * manuellement" badge.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('candidats', function (Blueprint $table): void {
            $table->text('admission_note')->nullable()->after('admis_at');
        });
    }

    public function down(): void
    {
        Schema::table('candidats', function (Blueprint $table): void {
            $table->dropColumn('admission_note');
        });
    }
};
