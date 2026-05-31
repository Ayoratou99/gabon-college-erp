<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Single-column DESC indexes on the three audit-event timestamps.
 *
 * The unified audit-log admin (Journal d'audit) UNIONs three tables and
 * sorts the whole thing by event-time DESC. Each table already has
 * composite indexes where the timestamp is the *trailing* column — those
 * only help when there's also a filter on the leading column (user_id,
 * candidat_id, etc.). For the "give me the most-recent N rows across
 * every source" hot path, we need a single-column index on each
 * timestamp.
 *
 * Postgres uses these for ORDER BY ... LIMIT without a sort step.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('candidat_modifications', function (Blueprint $table): void {
            $table->index('changed_at', 'candidat_modifications_changed_at_idx');
        });
        Schema::table('setting_change_logs', function (Blueprint $table): void {
            $table->index('changed_at', 'setting_change_logs_changed_at_idx');
        });
        Schema::table('login_attempts', function (Blueprint $table): void {
            $table->index('attempted_at', 'login_attempts_attempted_at_idx');
        });
    }

    public function down(): void
    {
        Schema::table('candidat_modifications', function (Blueprint $table): void {
            $table->dropIndex('candidat_modifications_changed_at_idx');
        });
        Schema::table('setting_change_logs', function (Blueprint $table): void {
            $table->dropIndex('setting_change_logs_changed_at_idx');
        });
        Schema::table('login_attempts', function (Blueprint $table): void {
            $table->dropIndex('login_attempts_attempted_at_idx');
        });
    }
};
