<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add a per-document review state to candidat_documents.
 *
 *   review_status        en_attente | valide | a_refaire
 *   reviewed_at          when the chef-centre / admin clicked the button
 *   reviewed_by_user_id  who clicked it
 *   review_comment       free-text justification (required when status = a_refaire,
 *                        so the candidat knows what to fix on resubmission)
 *
 * Defaults: existing rows + newly uploaded documents start at `en_attente`.
 * The admin candidat-detail page shows status badges per doc and exposes
 * Approve / Reject buttons that hit the review endpoint.
 *
 * "manquant" is intentionally NOT a row state — it's implicit (no row in
 * candidat_documents for a required documents_requis code).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('candidat_documents', function (Blueprint $table): void {
            $table->string('review_status', 20)->default('en_attente')->after('uploaded_at');
            $table->timestamp('reviewed_at')->nullable()->after('review_status');
            $table->uuid('reviewed_by_user_id')->nullable()->after('reviewed_at');
            $table->string('review_comment', 500)->nullable()->after('reviewed_by_user_id');

            $table->index(['candidat_id', 'review_status']);
            $table->foreign('reviewed_by_user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('candidat_documents', function (Blueprint $table): void {
            $table->dropForeign(['reviewed_by_user_id']);
            $table->dropIndex(['candidat_id', 'review_status']);
            $table->dropColumn(['review_status', 'reviewed_at', 'reviewed_by_user_id', 'review_comment']);
        });
    }
};
