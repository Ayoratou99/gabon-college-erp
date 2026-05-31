<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Two changes to support the "candidat-promoted-to-étudiant" flow:
     *
     *   1. `password` becomes nullable — newly-promoted students have no
     *      password yet; they set one through the first-login wizard.
     *   2. `must_set_password` flags those promoted accounts so the login
     *      controller can redirect them to the activation flow even if a
     *      stub password ends up populated.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('password')->nullable()->change();
            $table->boolean('must_set_password')->default(false)->after('password_legacy');
            $table->uuid('promoted_from_candidat_id')->nullable()->after('current_session_id');
            $table->index('promoted_from_candidat_id', 'users_promoted_from_candidat_idx');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex('users_promoted_from_candidat_idx');
            $table->dropColumn(['must_set_password', 'promoted_from_candidat_id']);
            $table->string('password')->nullable(false)->change();
        });
    }
};
