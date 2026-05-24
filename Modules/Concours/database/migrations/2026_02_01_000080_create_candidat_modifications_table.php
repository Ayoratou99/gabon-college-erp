<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Field-level audit log for candidate dossier edits.
     *
     * Three sources of mutation are tracked:
     *   - public: the candidate uses the email+telephone modification flow
     *             (user_id is NULL, channel='public')
     *   - back-office: chef-centre / DE edits via admin UI (channel='admin')
     *   - system: status changes from payment callbacks (channel='system')
     */
    public function up(): void
    {
        Schema::create('candidat_modifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('candidat_id');
            $table->uuid('user_id')->nullable();
            $table->string('channel', 20)->default('admin');  // public | admin | system
            $table->string('field', 100);
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->text('reason')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('changed_at')->useCurrent();
            $table->timestamps();

            $table->index(['candidat_id', 'changed_at']);
            $table->index(['user_id', 'changed_at']);
            $table->index(['channel', 'changed_at']);

            $table->foreign('candidat_id')->references('id')->on('candidats')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('candidat_modifications');
    }
};
