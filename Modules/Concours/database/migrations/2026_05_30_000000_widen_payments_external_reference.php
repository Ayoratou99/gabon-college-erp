<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The original `external_reference` was VARCHAR(100), sized for an opaque
 * ULID-derived string. We've moved to AES-256-GCM-encrypted references
 * (PaymentReferenceCipher) since eBilling does NOT sign callback bodies —
 * the reference is now our only authenticity proof, and the ciphertext
 * (base64url'd) is ~180 chars. Bump to 255 to leave headroom for future
 * payload additions (e.g. session_id) without a second migration.
 *
 * Existing rows keep their values; the column just gets wider.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->string('external_reference', 255)->change();
        });
    }

    public function down(): void
    {
        // Reverting risks truncating rows. We accept the asymmetry — the
        // up path is the supported direction.
        Schema::table('payments', function (Blueprint $table): void {
            $table->string('external_reference', 100)->change();
        });
    }
};
