<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Parametrage\Http\Controllers\SettingController;

Route::middleware('web')->group(function (): void {

    // Public read-only endpoint — used by the homepage to render dynamic content.
    Route::get('/api/parametrage/public', [SettingController::class, 'publicMap'])
        ->name('parametrage.public');

    Route::middleware(['auth', 'twofactor'])->prefix('admin/parametrage')->name('admin.parametrage.')->group(function (): void {
        Route::get('/',                  [SettingController::class, 'index'])->name('index')
            ->middleware('perm:view:parametrage:*');
        Route::get('/{setting}',         [SettingController::class, 'show'])->name('show')
            ->middleware('perm:view:parametrage:*');
        Route::put('/{setting}',         [SettingController::class, 'update'])->name('update')
            ->middleware('perm:edit:parametrage:*');
    });
});
