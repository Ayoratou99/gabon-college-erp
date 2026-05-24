<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('types_epreuves', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('code', 30)->unique();              // 'ecrit', 'oral', 'pratique', 'epreuve-sportive'
            $table->string('libelle', 100);
            $table->text('description')->nullable();
            $table->string('modalite', 20);                    // ecrit | oral | pratique | mixte
            $table->unsignedSmallInteger('duree_minutes_defaut')->default(120);
            $table->decimal('coefficient_defaut', 4, 2)->default(1.00);
            $table->boolean('active')->default(true);
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['active', 'modalite']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('types_epreuves');
    }
};
