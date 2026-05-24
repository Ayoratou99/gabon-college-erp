<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Concours\Http\Controllers\Admin\CandidatController;
use Modules\Concours\Http\Controllers\Admin\EpreuveController;
use Modules\Concours\Http\Controllers\Admin\NoteController;
use Modules\Concours\Http\Controllers\Admin\Pages\CandidatPageController;
use Modules\Concours\Http\Controllers\Admin\Pages\EpreuvePageController;
use Modules\Concours\Http\Controllers\Admin\Pages\NotePageController;
use Modules\Concours\Http\Controllers\Admin\Pages\SelectionPageController;
use Modules\Concours\Http\Controllers\Admin\PlanningController;
use Modules\Concours\Http\Controllers\Admin\SelectionController;
use Modules\Concours\Http\Controllers\Public\CandidatDashboardController;
use Modules\Concours\Http\Controllers\Public\CandidatLookupController;
use Modules\Concours\Http\Controllers\Public\PaymentCallbackController;
use Modules\Concours\Http\Controllers\Public\RegistrationController;

Route::middleware('web')->group(function (): void {

    // -------- Public registration --------
    Route::prefix('inscription')->name('concours.')->group(function (): void {
        Route::get('/',                    [RegistrationController::class, 'showForm'])->name('inscription.form');
        Route::post('/',                   [RegistrationController::class, 'submit'])
            ->middleware('throttle:6,1')
            ->name('inscription.submit');
        Route::get('/succes/{matricule}',  [RegistrationController::class, 'success'])->name('inscription.success');
        Route::get('/inscriptions-fermees',[RegistrationController::class, 'closed'])->name('inscriptions.fermees');
    });

    // -------- Public lookup --------
    Route::get('/verifier-demande',  [CandidatLookupController::class, 'showStatusForm'])->name('concours.public.status.form');
    Route::post('/verifier-demande', [CandidatLookupController::class, 'status'])
        ->middleware('throttle:30,1')->name('concours.public.status');

    Route::get('/recuperer-dossier', [CandidatLookupController::class, 'showModifyForm'])->name('concours.public.lookup.form');
    Route::post('/recuperer-dossier',[CandidatLookupController::class, 'submitLookup'])
        ->middleware('throttle:10,1')->name('concours.public.lookup.submit');

    // Token-gated edit form (candidat ID resolved from session, not the URL).
    Route::get('/modifier-dossier/{token}',  [CandidatLookupController::class, 'showEditForm'])
        ->where('token', '[A-Z0-9]{26}')
        ->name('concours.public.modify.form');
    Route::post('/modifier-dossier/{token}', [CandidatLookupController::class, 'submitEdit'])
        ->where('token', '[A-Z0-9]{26}')
        ->middleware('throttle:6,1')
        ->name('concours.public.modify.submit');
    Route::get('/modifier-dossier-succes/{matricule}', [CandidatLookupController::class, 'editSuccess'])
        ->where('matricule', 'CUK-[A-Z0-9]{12}')
        ->name('concours.public.modify.success');

    // -------- eBilling callback (no CSRF, server-to-server) --------
    Route::post('/payment/ebilling/callback', PaymentCallbackController::class)
        ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class])
        ->name('concours.payment.callback');

    // -------- Public candidate dashboard + résultats publics --------
    Route::get('/candidat/{matricule}', [CandidatDashboardController::class, 'dashboard'])
        ->where('matricule', 'CUK-[A-Z0-9]{12}')
        ->name('concours.public.candidat.dashboard');

    Route::get('/resultats', [CandidatDashboardController::class, 'results'])
        ->name('concours.public.results');

    // -------- Admin HTML pages (auth + 2FA) --------
    Route::middleware(['auth', 'twofactor'])
        ->prefix('admin/concours')
        ->name('admin.pages.concours.')
        ->group(function (): void {
            Route::get('/candidats',                       [CandidatPageController::class, 'index'])->name('candidats.index');
            Route::get('/candidats/export.{format}',       [CandidatPageController::class, 'export'])
                ->where('format', 'xlsx|csv|pdf')->name('candidats.export');
            Route::get('/candidats/{candidat}',            [CandidatPageController::class, 'show'])->name('candidats.show');

            Route::get('/epreuves',             [EpreuvePageController::class, 'index'])->name('epreuves.index');

            Route::get('/notes',                [NotePageController::class, 'picker'])->name('notes.picker');
            Route::get('/notes/{epreuve}',      [NotePageController::class, 'grid'])->name('notes.grid');

            Route::get('/selection',            [SelectionPageController::class, 'wizard'])->name('selection.wizard');
        });

    // -------- Admin JSON API (auth + 2FA + RBAC) --------
    Route::middleware(['auth', 'twofactor'])
        ->prefix('api/admin/concours')
        ->name('admin.concours.')
        ->group(function (): void {
            // Candidats (stage 5A)
            Route::get('/candidats',             [CandidatController::class, 'index'])->name('candidats.index');
            Route::get('/candidats/{candidat}',  [CandidatController::class, 'show'])->name('candidats.show');
            Route::post('/candidats/{candidat}/decide', [CandidatController::class, 'decide'])->name('candidats.decide');

            // Épreuves
            Route::get('/epreuves',              [EpreuveController::class, 'index'])->name('epreuves.index');
            Route::post('/epreuves',             [EpreuveController::class, 'store'])->name('epreuves.store');
            Route::get('/epreuves/{epreuve}',    [EpreuveController::class, 'show'])->name('epreuves.show');
            Route::put('/epreuves/{epreuve}',    [EpreuveController::class, 'update'])->name('epreuves.update');
            Route::delete('/epreuves/{epreuve}', [EpreuveController::class, 'destroy'])->name('epreuves.destroy');

            // Planning
            Route::get('/plannings',                  [PlanningController::class, 'index'])->name('plannings.index');
            Route::post('/plannings',                 [PlanningController::class, 'store'])->name('plannings.store');
            Route::delete('/plannings/{planning}',    [PlanningController::class, 'destroy'])->name('plannings.destroy');

            // Notes
            Route::get('/notes/grid',          [NoteController::class, 'grid'])->name('notes.grid');
            Route::post('/notes/batch',        [NoteController::class, 'saveBatch'])->name('notes.batch');
            Route::post('/notes/recompute',    [NoteController::class, 'recompute'])->name('notes.recompute');

            // Sélection / Publication
            Route::get('/selection/suggest',       [SelectionController::class, 'suggest'])->name('selection.suggest');
            Route::post('/selection/confirm',      [SelectionController::class, 'confirm'])->name('selection.confirm');
            Route::get('/selection/publication',   [SelectionController::class, 'publication'])->name('selection.publication');
        });
});
