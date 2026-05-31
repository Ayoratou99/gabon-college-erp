<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add a "blocked" state to users.
 *
 * Distinct from soft-delete: a blocked user still exists, still has roles,
 * still appears in admin lists and audit trails — they just can't sign in.
 * The block can be lifted by any admin / DG / DE with `edit:users:*`.
 *
 *   blocked_at         when the block was applied (null = active)
 *   blocked_reason     free-text justification shown in the audit trail
 *   blocked_by_user_id who pressed the button (null when restored from import)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('blocked_at')->nullable()->after('last_login_ip');
            $table->string('blocked_reason', 500)->nullable()->after('blocked_at');
            $table->uuid('blocked_by_user_id')->nullable()->after('blocked_reason');

            $table->index('blocked_at');
            $table->foreign('blocked_by_user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropForeign(['blocked_by_user_id']);
            $table->dropIndex(['blocked_at']);
            $table->dropColumn(['blocked_at', 'blocked_reason', 'blocked_by_user_id']);
        });
    }
};
