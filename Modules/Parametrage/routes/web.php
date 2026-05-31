<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Parametrage\Http\Controllers\Admin\Pages\SettingsPageController;
use Modules\Parametrage\Http\Controllers\SettingController;

Route::middleware('web')->group(function (): void {

    // Public read-only endpoint — used by the homepage to render dynamic content.
    Route::get('/api/parametrage/public', [SettingController::class, 'publicMap'])
        ->name('parametrage.public');

    // HTML admin page (the back-office grid).
    Route::middleware(['auth', 'twofactor', 'active.role'])->group(function (): void {
        Route::get('/admin/parametrage', [SettingsPageController::class, 'index'])
            ->middleware('perm:view:parametrage:*')
            ->name('admin.pages.parametrage.index');

        // Audit-log browse (reads from setting_change_logs).
        Route::get('/admin/parametrage/historique', [SettingsPageController::class, 'history'])
            ->middleware('perm:view:parametrage:*')
            ->name('admin.pages.parametrage.history');

        // Inline file upload for image_url settings.
        Route::post('/admin/parametrage/{setting}/upload', [SettingsPageController::class, 'upload'])
            ->middleware(['perm:edit:parametrage:*', 'throttle:30,1'])
            ->name('admin.pages.parametrage.upload');
    });

    // JSON API used by the page's inline-save (PUT only — list/show go through the page).
    Route::middleware(['auth', 'twofactor', 'active.role'])->prefix('admin/parametrage')->name('admin.parametrage.')->group(function (): void {
        Route::get('/api/list',          [SettingController::class, 'index'])->name('api.index')
            ->middleware('perm:view:parametrage:*');
        Route::get('/api/{setting}',     [SettingController::class, 'show'])->name('api.show')
            ->middleware('perm:view:parametrage:*');
        Route::put('/{setting}',         [SettingController::class, 'update'])->name('update')
            ->middleware('perm:edit:parametrage:*');
    });
});
