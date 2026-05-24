<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('niveaux', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('cycle_id');
            $table->string('code', 20);                    // DUT1, DUT2, L1, L2, M1...
            $table->string('libelle', 100);
            $table->unsignedSmallInteger('ordre');         // 1, 2, 3 within the cycle
            $table->boolean('est_niveau_entree')->default(false); // marks the niveau new students enter
            $table->boolean('active')->default(true);
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['cycle_id', 'code']);          // DUT1 only once per cycle
            $table->index(['cycle_id', 'ordre']);
            $table->index(['active', 'display_order']);

            $table->foreign('cycle_id')->references('id')->on('cycles')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('niveaux');
    }
};
