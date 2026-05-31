<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Flags the prod QA "test candidate" (config('concours.test')). A test row is
 * hidden from staff dashboards/reporting/lists for everyone but super-admin,
 * and its eBilling invoice is charged the reduced test fee.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('candidats', function (Blueprint $table): void {
            $table->boolean('is_test')->default(false)->index()->after('matricule_public');
        });
    }

    public function down(): void
    {
        Schema::table('candidats', function (Blueprint $table): void {
            $table->dropColumn('is_test');
        });
    }
};
