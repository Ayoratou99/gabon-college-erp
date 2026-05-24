<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cycles', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('code', 20)->unique();           // DUT, LICENCE, MASTER
            $table->string('nom', 100);
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('duree_annees');   // 2 for DUT, 3 for licence, etc.
            $table->boolean('active')->default(true);
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['active', 'display_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cycles');
    }
};
