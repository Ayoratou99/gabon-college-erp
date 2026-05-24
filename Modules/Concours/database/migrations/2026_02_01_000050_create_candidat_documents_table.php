<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candidat_documents', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('candidat_id');
            $table->uuid('document_requis_id');
            $table->string('file_path', 500);
            $table->string('disk', 50)->default('local');
            $table->string('mime_type', 100);
            $table->unsignedInteger('size_bytes');
            $table->string('original_name', 191)->nullable();
            $table->string('sha256', 64)->nullable(); // for dedup / tamper detection
            $table->timestamp('uploaded_at')->useCurrent();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['candidat_id', 'document_requis_id']);
            $table->index('sha256');

            $table->foreign('candidat_id')->references('id')->on('candidats')->cascadeOnDelete();
            $table->foreign('document_requis_id')->references('id')->on('documents_requis')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('candidat_documents');
    }
};
