<?php

declare(strict_types=1);

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PublicPagesController;
use Illuminate\Support\Facades\Route;

// Module-specific route files are loaded by their own ServiceProviders
// (see Modules/*/Providers/*ServiceProvider::boot()).

Route::get('/', fn () => view('welcome'))->name('home');

// Public content pages.
Route::get('/documents-officiels', [PublicPagesController::class, 'documentsOfficiels'])->name('documents.officiels');
Route::get('/documents-officiels/{index}/view', [PublicPagesController::class, 'documentView'])->whereNumber('index')->name('documents.officiels.view');
Route::get('/documents-officiels/{index}/download', [PublicPagesController::class, 'documentDownload'])->whereNumber('index')->name('documents.officiels.download');
Route::get('/annonce', [PublicPagesController::class, 'annonce'])->name('annonce');
Route::get('/annonce/flyer', [PublicPagesController::class, 'annonceFlyer'])->name('annonce.flyer');

Route::get('/dashboard', DashboardController::class)
    ->middleware(['auth', 'twofactor', 'active.role'])
    ->name('dashboard');
