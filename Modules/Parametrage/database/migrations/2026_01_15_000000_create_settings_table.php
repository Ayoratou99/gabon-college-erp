<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table): void {
            $table->uuid('id')->primary();

            // Dotted key, e.g. "concours.fee.amount". Always lowercase.
            $table->string('key', 191)->unique();
            $table->string('category', 50)->index();   // "concours", "site", ...
            $table->string('type', 20);                // string|integer|decimal|boolean|json|image_url|email|phone|url|text

            // Stored as raw TEXT; the caster handles type coercion. Encrypted
            // values are stored ciphertext-prefixed-and-base64 by the model cast.
            $table->text('value')->nullable();
            $table->text('default_value')->nullable();

            $table->string('label')->nullable();        // human-readable, French
            $table->text('description')->nullable();    // help text shown in UI
            $table->json('validation_rules')->nullable(); // Laravel-style rules array

            $table->boolean('is_encrypted')->default(false); // encryption at rest
            $table->boolean('is_public')->default(false);    // exposable to unauth visitors
            $table->boolean('is_system')->default(false);    // can't be deleted via UI
            $table->unsignedSmallInteger('display_order')->default(0);

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
