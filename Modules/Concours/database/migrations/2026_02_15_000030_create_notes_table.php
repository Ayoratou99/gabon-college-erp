<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notes', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('candidat_id');
            $table->uuid('epreuve_id');

            $table->decimal('valeur', 5, 2)->nullable();   // null = absent / not yet entered
            $table->boolean('absent')->default(false);
            $table->boolean('locked')->default(false);     // chef-centre locks after entry; only DE/DG can unlock

            $table->uuid('entered_by_user_id')->nullable();
            $table->timestamp('entered_at')->nullable();
            $table->uuid('updated_by_user_id')->nullable();
            $table->text('commentaire')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['candidat_id', 'epreuve_id']);
            $table->index(['epreuve_id', 'locked']);
            $table->index(['candidat_id', 'valeur']);

            $table->foreign('candidat_id')->references('id')->on('candidats')->cascadeOnDelete();
            $table->foreign('epreuve_id')->references('id')->on('epreuves')->cascadeOnDelete();
            $table->foreign('entered_by_user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by_user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notes');
    }
};
