<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Official documents get their own table (with a proper back-office CRUD)
 * instead of living as a raw JSON `site.documents_officiels` setting. We
 * migrate any seeded entries into the table and drop the setting.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents_officiels', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('title', 191);
            $table->string('file_path', 500);
            $table->string('file_disk', 50)->default('public');
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->unsignedInteger('display_order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['active', 'display_order']);
        });

        // --- Migrate the old JSON setting into the table, then drop it ---
        $settingId = DB::table('settings')->where('key', 'site.documents_officiels')->value('id');
        if ($settingId !== null) {
            $docs = json_decode((string) DB::table('settings')->where('id', $settingId)->value('value'), true);
            if (is_array($docs)) {
                $now = now();
                $order = 0;
                foreach ($docs as $d) {
                    $file = (string) ($d['file'] ?? '');
                    if ($file === '') {
                        continue;
                    }
                    DB::table('documents_officiels')->insert([
                        'id'            => (string) Str::uuid(),
                        'title'         => (string) ($d['title'] ?? 'Document'),
                        'file_path'     => $file,
                        'file_disk'     => 'public',
                        'display_order' => $order++,
                        'active'        => true,
                        'created_at'    => $now,
                        'updated_at'    => $now,
                    ]);
                }
            }
            // setting_change_logs rows cascade via the FK.
            DB::table('settings')->where('id', $settingId)->delete();
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('documents_officiels');
    }
};
