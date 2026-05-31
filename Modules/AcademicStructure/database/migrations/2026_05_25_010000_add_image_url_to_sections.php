<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Optional cover image for each formation/section — displayed on the
     * public home "Nos formations" grid. Falls back to one of the bundled
     * CUK photos if NULL, so existing rows render fine without backfill.
     */
    public function up(): void
    {
        Schema::table('sections', function (Blueprint $table): void {
            $table->string('image_url', 500)->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('sections', function (Blueprint $table): void {
            $table->dropColumn('image_url');
        });
    }
};
