<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Reporting\Http\Controllers\Admin\ReportingController;

Route::middleware(['web', 'auth', 'twofactor'])->group(function (): void {

    // Dashboard shell (HTML)
    Route::get('/admin/reporting', [ReportingController::class, 'dashboard'])
        ->name('admin.pages.reporting.dashboard');

    // Chart data (JSON, fetched by Alpine on mount)
    Route::prefix('api/admin/reporting')->name('admin.reporting.')->group(function (): void {
        Route::get('/by-status',     [ReportingController::class, 'apiByStatus'])->name('by-status');
        Route::get('/by-centre',     [ReportingController::class, 'apiByCentre'])->name('by-centre');
        Route::get('/by-section',    [ReportingController::class, 'apiBySection'])->name('by-section');
        Route::get('/by-series-bac', [ReportingController::class, 'apiBySeriesBac'])->name('by-series-bac');
        Route::get('/by-sex',        [ReportingController::class, 'apiBySex'])->name('by-sex');
        Route::get('/timeline',      [ReportingController::class, 'apiTimeline'])->name('timeline');
    });
});
