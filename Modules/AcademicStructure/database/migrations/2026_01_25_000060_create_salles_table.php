<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salles', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('departement_id')->nullable();
            $table->string('code', 30)->unique();
            $table->string('nom', 191);
            $table->unsignedInteger('capacite');             // seats
            $table->string('type', 20)->default('salle');    // salle | amphi | labo | td | examen
            $table->string('batiment', 100)->nullable();
            $table->string('etage', 20)->nullable();
            $table->boolean('accessible_pmr')->default(false);
            $table->text('notes')->nullable();
            $table->boolean('active')->default(true);
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['active', 'type']);
            $table->index(['departement_id', 'active']);
            $table->index('capacite');

            $table->foreign('departement_id')->references('id')->on('departements')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salles');
    }
};
