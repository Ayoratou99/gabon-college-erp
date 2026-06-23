<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\UserManagement\Http\Controllers\Admin\Pages\AuditLogPageController;
use Modules\UserManagement\Http\Controllers\Admin\Pages\LoginAttemptPageController;
use Modules\UserManagement\Http\Controllers\Admin\Pages\RolePageController;
use Modules\UserManagement\Http\Controllers\Admin\Pages\UserPageController;
use Modules\UserManagement\Http\Controllers\Auth\FirstLoginController;
use Modules\UserManagement\Http\Controllers\Auth\LoginController;
use Modules\UserManagement\Http\Controllers\Auth\RolePickerController;
use Modules\UserManagement\Http\Controllers\Auth\TwoFactorController;

Route::middleware('web')->group(function (): void {

    // ---- Guest auth flow ----
    Route::middleware('guest')->group(function (): void {
        Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
        Route::post('/login', [LoginController::class, 'login'])
            ->middleware(['recaptcha', 'throttle:10,1'])
            ->name('login.attempt');

        // ---- First-login activation (admis candidat → étudiant account) ----
        Route::prefix('connexion/premiere-fois')->name('first-login.')->group(function (): void {
            Route::get('/',             [FirstLoginController::class, 'showIdentifyForm'])->name('start');
            Route::post('/',            [FirstLoginController::class, 'submitIdentify'])
                ->middleware('throttle:6,1')
                ->name('start.submit');
            Route::get('/mot-de-passe', [FirstLoginController::class, 'showPasswordForm'])->name('password.form');
            Route::post('/mot-de-passe',[FirstLoginController::class, 'submitPassword'])->name('password.submit');
            Route::get('/2fa',          [FirstLoginController::class, 'showTwoFactorForm'])->name('2fa.form');
            Route::post('/2fa',         [FirstLoginController::class, 'submitTwoFactor'])->name('2fa.submit');
        });
    });

    // ---- 2FA (pre-auth user in session, NOT yet Auth::user()) ----
    Route::prefix('two-factor')->name('two-factor.')->group(function (): void {
        Route::get('/enroll',    [TwoFactorController::class, 'showEnrollForm'])->name('enroll');
        Route::post('/enroll',   [TwoFactorController::class, 'submitEnroll']);
        Route::get('/challenge', [TwoFactorController::class, 'showChallengeForm'])->name('challenge');
        Route::post('/challenge',[TwoFactorController::class, 'submitChallenge']);
    });

    // ---- Role picker (auth + 2FA, but NOT active.role — this IS where it
    //      gets resolved). Always reachable for any logged-in user, so the
    //      EnsureActiveRole middleware can safely redirect here without an
    //      infinite loop. ----
    Route::middleware(['auth', 'twofactor'])->group(function (): void {
        Route::get('/choisir-role',  [RolePickerController::class, 'show'])->name('role.picker');
        Route::post('/choisir-role', [RolePickerController::class, 'select'])->name('role.select');
        Route::get('/changer-role',  [RolePickerController::class, 'switch'])->name('role.switch');
    });

    // ---- Authenticated ----
    Route::middleware(['auth', 'twofactor', 'active.role'])->group(function (): void {
        Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

        // -------- Admin security pages --------
        Route::prefix('admin')->name('admin.pages.')->group(function (): void {
            // Users
            Route::get('/users',                          [UserPageController::class, 'index'])->name('users.index');
            Route::post('/users/data',                    [UserPageController::class, 'data'])->name('users.data');
            Route::get('/users/{user}',                   [UserPageController::class, 'show'])->name('users.show');
            Route::post('/users/{user}/roles',            [UserPageController::class, 'syncRoles'])->name('users.roles');
            Route::post('/users/{user}/reset-2fa',        [UserPageController::class, 'reset2fa'])->name('users.reset2fa');
            Route::post('/users/{user}/reset-password',   [UserPageController::class, 'resetPassword'])->name('users.resetPassword');
            Route::post('/users/{user}/toggle-block',     [UserPageController::class, 'toggleBlock'])->name('users.toggleBlock');

            // Roles & permissions — audit catalog + super-admin permission editor.
            Route::get('/roles',                          [RolePageController::class, 'index'])->name('roles.index');
            Route::put('/roles/{role}/permissions',       [RolePageController::class, 'updatePermissions'])->name('roles.permissions.update');

            // Login attempts audit
            Route::get('/login-attempts',                 [LoginAttemptPageController::class, 'index'])->name('login-attempts.index');
            Route::post('/login-attempts/data',           [LoginAttemptPageController::class, 'data'])->name('login-attempts.data');

            // Journal d'audit — unified search over candidat_modifications,
            // setting_change_logs, login_attempts.
            Route::get('/audit-log',           [AuditLogPageController::class, 'index'])->name('audit-log.index');
            Route::post('/audit-log/data',     [AuditLogPageController::class, 'data'])->name('audit-log.data');
            Route::get('/audit-log/export.csv', [AuditLogPageController::class, 'export'])->name('audit-log.export');
        });
    });
});
