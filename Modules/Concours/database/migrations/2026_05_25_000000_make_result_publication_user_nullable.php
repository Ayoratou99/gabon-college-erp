<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Historical / legacy publications have no "published by" user — they
     * were produced under the old PHP system. Make the column nullable so
     * archive imports can attach a PDF to a session without inventing a
     * fake author user.
     */
    public function up(): void
    {
        Schema::table('result_publications', function (Blueprint $table): void {
            $table->uuid('published_by_user_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('result_publications', function (Blueprint $table): void {
            $table->uuid('published_by_user_id')->nullable(false)->change();
        });
    }
};
