<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->uuid('id')->primary();

            $table->string('nom', 100);
            $table->string('prenom', 100);

            // Either email OR telephone is required at the application layer; both
            // are nullable here so we can support pre-electronic admin accounts
            // (the legacy users table has `tel` as the login and no email).
            $table->string('email', 191)->nullable();
            $table->string('telephone', 30)->nullable();

            $table->string('password');                        // bcrypt OR legacy SHA1
            $table->boolean('password_legacy')->default(false); // true while SHA1 not yet rehashed

            $table->text('google2fa_secret')->nullable();      // encrypted via the cast
            $table->timestamp('google2fa_confirmed_at')->nullable();

            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();

            $table->uuid('current_session_id')->nullable(); // concours session bound at login

            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });

        // Case-insensitive partial unique indexes — Postgres-native, much faster
        // than CHECK-driven uniqueness and tolerant of soft-deletes/null values.
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX users_email_unique
            ON users (LOWER(email))
            WHERE email IS NOT NULL AND deleted_at IS NULL
        SQL);

        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX users_telephone_unique
            ON users (telephone)
            WHERE telephone IS NOT NULL AND deleted_at IS NULL
        SQL);

        // Covering index for login lookups (we always filter by deleted_at IS NULL).
        DB::statement(<<<'SQL'
            CREATE INDEX users_active_lookup
            ON users (LOWER(email), telephone)
            WHERE deleted_at IS NULL
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
