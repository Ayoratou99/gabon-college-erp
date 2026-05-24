<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candidat_motifs_rejet', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('candidat_id');
            $table->text('motif');
            $table->uuid('decided_by_user_id')->nullable();
            $table->timestamp('decided_at')->useCurrent();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['candidat_id', 'decided_at']);

            $table->foreign('candidat_id')->references('id')->on('candidats')->cascadeOnDelete();
            $table->foreign('decided_by_user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('candidat_motifs_rejet');
    }
};
