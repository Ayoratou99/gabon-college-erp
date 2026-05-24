<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('candidat_id');
            $table->uuid('concours_session_id');
            $table->unsignedInteger('amount');         // FCFA, integer
            $table->string('currency', 5)->default('FCFA');
            $table->string('ebilling_id', 100)->nullable();
            $table->string('external_reference', 100);
            $table->string('status', 20)->default('INIT'); // INIT | PENDING | PAID | FAILED
            $table->jsonb('payload')->nullable();      // last callback body for forensics
            $table->timestamp('paid_at')->nullable();
            $table->string('callback_ip', 45)->nullable();
            $table->boolean('signature_verified')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->unique('external_reference');
            $table->index(['candidat_id', 'status']);
            $table->index(['concours_session_id', 'status']);
            $table->index('ebilling_id');

            $table->foreign('candidat_id')->references('id')->on('candidats')->cascadeOnDelete();
            $table->foreign('concours_session_id')->references('id')->on('concours_sessions')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
