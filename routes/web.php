<?php

declare(strict_types=1);

use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

// Module-specific route files are loaded by their own ServiceProviders
// (see Modules/*/Providers/*ServiceProvider::boot()).

Route::get('/', fn () => view('welcome'))->name('home');

Route::get('/dashboard', DashboardController::class)
    ->middleware(['auth', 'twofactor', 'active.role'])
    ->name('dashboard');
