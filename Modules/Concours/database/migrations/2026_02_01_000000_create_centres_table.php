<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Centres d'examen — registered ONCE and reused across concours sessions.
     * The legacy system recreated centres + chefs per year, which led to
     * orphan rows. The new design joins centres ↔ sessions via
     * concours_session_centres.
     */
    public function up(): void
    {
        Schema::create('centres', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('province_id')->nullable();
            $table->string('code', 30)->unique();
            $table->string('nom', 100);
            $table->string('ville', 100)->nullable();
            $table->text('adresse')->nullable();
            $table->unsignedInteger('capacite_par_defaut')->default(200);
            $table->boolean('active')->default(true);
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['active', 'display_order']);
            $table->index(['province_id', 'active']);

            $table->foreign('province_id')->references('id')->on('provinces')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('centres');
    }
};
