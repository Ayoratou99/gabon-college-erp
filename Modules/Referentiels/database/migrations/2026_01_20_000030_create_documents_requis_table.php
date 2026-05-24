<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents_requis', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('code', 30)->unique();            // 'acte', 'colebac', etc.
            $table->string('libelle', 191);                  // "Copie légalisée de l'acte de naissance"
            $table->text('description')->nullable();         // help text shown in the upload UI
            $table->json('formats_acceptes')->nullable();    // ['pdf','jpg','jpeg','png']
            $table->unsignedInteger('taille_max_ko')->default(5120);
            $table->boolean('obligatoire')->default(true);
            $table->boolean('active')->default(true);
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['active', 'display_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents_requis');
    }
};
