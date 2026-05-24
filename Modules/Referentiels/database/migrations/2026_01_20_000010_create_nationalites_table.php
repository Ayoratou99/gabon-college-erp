<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nationalites', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('code_iso', 3)->unique();   // ISO 3166-1 alpha-2/3
            $table->string('nom', 100);
            $table->boolean('active')->default(true);
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['active', 'nom']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nationalites');
    }
};
