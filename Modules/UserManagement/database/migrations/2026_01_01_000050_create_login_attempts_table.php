<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Audit trail of login attempts. Throttling itself runs against Redis
     * (sub-millisecond) — this table is the slow but durable record
     * surfaced in the back-office "recent attempts" view.
     */
    public function up(): void
    {
        Schema::create('login_attempts', function (Blueprint $table): void {
            $table->uuid('id')->primary();

            $table->string('identifier', 191);   // email or telephone tried
            $table->string('ip_address', 45);
            $table->string('user_agent', 512)->nullable();
            $table->uuid('user_id')->nullable();
            $table->boolean('succeeded')->default(false);
            $table->string('failure_reason', 100)->nullable();
            $table->timestamp('attempted_at')->useCurrent();

            $table->index(['identifier', 'ip_address', 'attempted_at']);
            $table->index(['user_id', 'attempted_at']);
            $table->index(['succeeded', 'attempted_at']);

            $table->foreign('user_id')
                ->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('login_attempts');
    }
};
