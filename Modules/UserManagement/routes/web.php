<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\UserManagement\Http\Controllers\Auth\LoginController;
use Modules\UserManagement\Http\Controllers\Auth\TwoFactorController;

Route::middleware('web')->group(function (): void {

    // ---- Guest auth flow ----
    Route::middleware('guest')->group(function (): void {
        Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
        Route::post('/login', [LoginController::class, 'login'])
            ->middleware(['recaptcha', 'throttle:10,1'])
            ->name('login.attempt');
    });

    // ---- 2FA (pre-auth user in session, NOT yet Auth::user()) ----
    Route::prefix('two-factor')->name('two-factor.')->group(function (): void {
        Route::get('/enroll',    [TwoFactorController::class, 'showEnrollForm'])->name('enroll');
        Route::post('/enroll',   [TwoFactorController::class, 'submitEnroll']);
        Route::get('/challenge', [TwoFactorController::class, 'showChallengeForm'])->name('challenge');
        Route::post('/challenge',[TwoFactorController::class, 'submitChallenge']);
    });

    // ---- Authenticated ----
    Route::middleware(['auth', 'twofactor'])->group(function (): void {
        Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
    });
});
