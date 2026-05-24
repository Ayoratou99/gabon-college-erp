<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departements', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('faculte_id')->nullable();
            $table->string('code', 30)->unique();
            $table->string('nom', 191);
            $table->text('description')->nullable();
            $table->boolean('active')->default(true);
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['active', 'display_order']);
            $table->index(['faculte_id', 'active']);

            $table->foreign('faculte_id')->references('id')->on('facultes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('departements');
    }
};
