<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\AcademicStructure\Http\Controllers\AcademicResourceController;

Route::middleware('web')->group(function (): void {

    Route::get('/api/academic/{slug}/public', [AcademicResourceController::class, 'publicIndex'])
        ->where('slug', '[a-z\-]+')
        ->name('academic.public');

    Route::middleware(['auth', 'twofactor'])
        ->prefix('api/academic')
        ->name('admin.academic.')
        ->group(function (): void {
            Route::get('/{slug}',               [AcademicResourceController::class, 'index'])->name('index');
            Route::post('/{slug}',              [AcademicResourceController::class, 'store'])->name('store');
            Route::get('/{slug}/{id}',          [AcademicResourceController::class, 'show'])->name('show');
            Route::put('/{slug}/{id}',          [AcademicResourceController::class, 'update'])->name('update');
            Route::delete('/{slug}/{id}',       [AcademicResourceController::class, 'destroy'])->name('destroy');
            Route::post('/{slug}/{id}/restore', [AcademicResourceController::class, 'restore'])->name('restore');
        })->where(['slug' => '[a-z\-]+', 'id' => '[0-9a-f\-]{36}']);
});
