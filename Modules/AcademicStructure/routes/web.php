<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\AcademicStructure\Http\Controllers\AcademicPageController;
use Modules\AcademicStructure\Http\Controllers\AcademicResourceController;

Route::middleware('web')->group(function (): void {

    Route::get('/api/academic/{slug}/public', [AcademicResourceController::class, 'publicIndex'])
        ->where('slug', '[a-z\-]+')
        ->name('academic.public');

    // ---- Admin HTML pages + DataTables AJAX feed ----
    Route::middleware(['auth', 'twofactor', 'active.role'])
        ->prefix('admin/academic')
        ->name('admin.academic.')
        ->group(function (): void {
            // Inline image upload for `image_url` fields (e.g. section
            // illustration). Registered before the {slug} routes so it can't be
            // swallowed by them.
            Route::post('/uploads/image', [AcademicPageController::class, 'uploadImage'])->name('uploads.image');
            Route::post('/{slug}/data', [AcademicPageController::class, 'data'])->name('data');
            Route::get('/{slug}',       [AcademicPageController::class, 'index'])->name('index');
        })->where('slug', '[a-z\-]+');

    // ---- Admin JSON API (POST/PUT/DELETE) ----
    Route::middleware(['auth', 'twofactor', 'active.role'])
        ->prefix('api/academic')
        ->name('admin.academic.api.')
        ->group(function (): void {
            Route::get('/{slug}',               [AcademicResourceController::class, 'index'])->name('index');
            Route::post('/{slug}',              [AcademicResourceController::class, 'store'])->name('store');
            Route::get('/{slug}/{id}',          [AcademicResourceController::class, 'show'])->name('show');
            Route::put('/{slug}/{id}',          [AcademicResourceController::class, 'update'])->name('update');
            Route::delete('/{slug}/{id}',       [AcademicResourceController::class, 'destroy'])->name('destroy');
            Route::post('/{slug}/{id}/restore', [AcademicResourceController::class, 'restore'])->name('restore');
        })->where(['slug' => '[a-z\-]+', 'id' => '[0-9a-f\-]{36}']);
});
