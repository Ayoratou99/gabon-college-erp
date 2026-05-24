<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Who's chef de centre for which centre, for which session.
     *
     * The User.accessibleCentreIds() resolver queries this table to gate
     * the `own_center` scope. A chef can technically cover several centres
     * (replacement), and a centre can have multiple chefs (titulaire +
     * suppléant) — both are allowed by the composite PK.
     */
    public function up(): void
    {
        Schema::create('chef_centre_assignments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('concours_session_id');
            $table->uuid('centre_id');
            $table->uuid('user_id');
            $table->boolean('est_principal')->default(true);   // titulaire vs suppléant
            $table->timestamp('assigned_at')->useCurrent();
            $table->uuid('assigned_by_user_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['concours_session_id', 'centre_id', 'user_id'], 'cca_unique');
            $table->index(['user_id', 'concours_session_id']); // hot path: resolver lookup
            $table->index(['concours_session_id', 'centre_id']);

            $table->foreign('concours_session_id')->references('id')->on('concours_sessions')->cascadeOnDelete();
            $table->foreign('centre_id')->references('id')->on('centres')->restrictOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('assigned_by_user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chef_centre_assignments');
    }
};
