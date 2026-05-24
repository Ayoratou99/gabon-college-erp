<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Audit trail for every setting mutation. Encrypted setting values are
     * NEVER stored here (we record `[encrypted]` instead) so the log itself
     * can't leak a secret that admins can read.
     */
    public function up(): void
    {
        Schema::create('setting_change_logs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('setting_id');
            $table->uuid('user_id')->nullable(); // null if changed by a console seeder
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('changed_at')->useCurrent();

            $table->index(['setting_id', 'changed_at']);
            $table->index(['user_id', 'changed_at']);

            $table->foreign('setting_id')->references('id')->on('settings')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('setting_change_logs');
    }
};
