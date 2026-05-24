<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Referentiels\Http\Controllers\ReferentielController;

Route::middleware('web')->group(function (): void {

    // ---- Public read (no auth) — feeds the registration form drop-downs ----
    Route::get('/api/referentiels/{slug}/public', [ReferentielController::class, 'publicIndex'])
        ->where('slug', '[a-z\-]+')
        ->name('referentiels.public');

    // ---- Admin CRUD ----
    Route::middleware(['auth', 'twofactor'])->prefix('api/referentiels')->name('admin.referentiels.')->group(function (): void {
        Route::get('/{slug}',              [ReferentielController::class, 'index'])->name('index');
        Route::post('/{slug}',             [ReferentielController::class, 'store'])->name('store');
        Route::get('/{slug}/{id}',         [ReferentielController::class, 'show'])->name('show');
        Route::put('/{slug}/{id}',         [ReferentielController::class, 'update'])->name('update');
        Route::delete('/{slug}/{id}',      [ReferentielController::class, 'destroy'])->name('destroy');
        Route::post('/{slug}/{id}/restore',[ReferentielController::class, 'restore'])->name('restore');
    })->where(['slug' => '[a-z\-]+', 'id' => '[0-9a-f\-]{36}']);
});
