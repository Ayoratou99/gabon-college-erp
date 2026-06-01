<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Concours\Http\Controllers\Admin\CandidatController;
use Modules\Concours\Http\Controllers\Admin\CandidatDocumentController;
use Modules\Concours\Http\Controllers\Admin\EpreuveController;
use Modules\Concours\Http\Controllers\Admin\NoteController;
use Modules\Concours\Http\Controllers\Admin\Pages\CandidatPageController;
use Modules\Concours\Http\Controllers\Admin\Pages\CentrePageController;
use Modules\Concours\Http\Controllers\Admin\Pages\ChefCentreAssignmentPageController;
use Modules\Concours\Http\Controllers\Admin\Pages\DocumentRequisSectionMatrixPageController;
use Modules\Concours\Http\Controllers\Admin\Pages\EpreuvePageController;
use Modules\Concours\Http\Controllers\Admin\Pages\NotePageController;
use Modules\Concours\Http\Controllers\Admin\Pages\PaymentPageController;
use Modules\Concours\Http\Controllers\Admin\Pages\PlanningPageController;
use Modules\Concours\Http\Controllers\Admin\Pages\SelectionPageController;
use Modules\Concours\Http\Controllers\Admin\Pages\SessionPageController;
use Modules\Concours\Http\Controllers\Admin\PlanningController;
use Modules\Concours\Http\Controllers\Admin\SelectionController;
use Modules\Concours\Http\Controllers\Etudiant\EtudiantSpaceController;
use Modules\Concours\Http\Controllers\Public\CandidatDashboardController;
use Modules\Concours\Http\Controllers\Public\CandidatLookupController;
use Modules\Concours\Http\Controllers\Public\CandidatModificationWizardController;
use Modules\Concours\Http\Controllers\Public\PaymentCallbackController;
use Modules\Concours\Http\Controllers\Public\PaymentController;
use Modules\Concours\Http\Controllers\Public\RegistrationController;
use Modules\Concours\Http\Controllers\Public\RegistrationWizardController;

Route::middleware('web')->group(function (): void {

    // -------- Public registration (multi-step wizard) --------
    Route::prefix('inscription')->name('concours.')->group(function (): void {
        // Entry: redirect to the visitor's current step (or step 1).
        Route::get('/', [RegistrationWizardController::class, 'entry'])
            ->name('inscription.wizard.entry');

        // Legacy alias — the old single-page form name. Anything still
        // linking to `concours.inscription.form` lands on the wizard entry.
        Route::get('/form', fn () => redirect()->route('concours.inscription.wizard.entry'))
            ->name('inscription.form');

        // Terminal pages (declared before the catch-all step routes so the
        // literal paths win even though the {step} regex would already
        // refuse to match them).
        Route::get('/succes/{matricule}',   [RegistrationController::class, 'success'])->name('inscription.success');
        Route::get('/inscriptions-fermees', [RegistrationController::class, 'closed'])->name('inscriptions.fermees');

        Route::post('/reset', [RegistrationWizardController::class, 'reset'])
            ->name('inscription.wizard.reset');

        // Per-document AJAX staging endpoints. Each file uploads on its own
        // to bypass post_max_size limits — see InscriptionStagedDocuments.
        Route::post('/documents/stage', [RegistrationWizardController::class, 'stageDocument'])
            ->middleware('throttle:60,1')
            ->name('inscription.wizard.stage');
        Route::delete('/documents/stage/{code}', [RegistrationWizardController::class, 'unstageDocument'])
            ->where('code', '[A-Za-z0-9_\-]{1,60}')
            ->name('inscription.wizard.unstage');

        // Final-submit alias kept for any third-party deeplink that POSTs
        // straight to /inscription/submit — bounce them at the documents
        // step's submit URL.
        Route::post('/submit', fn () => redirect()->route('concours.inscription.wizard.show', ['step' => 'documents']))
            ->name('inscription.submit');

        // Step screens. The {step} regex hard-limits the parameter.
        Route::get('/{step}', [RegistrationWizardController::class, 'show'])
            ->where('step', 'identite|contact|bac|choix|documents')
            ->name('inscription.wizard.show');
        Route::post('/{step}', [RegistrationWizardController::class, 'submit'])
            ->where('step', 'identite|contact|bac|choix|documents')
            ->middleware('throttle:30,1')
            ->name('inscription.wizard.submit');
        Route::post('/{step}/back', [RegistrationWizardController::class, 'back'])
            ->where('step', 'identite|contact|bac|choix|documents')
            ->name('inscription.wizard.back');
    });

    // -------- Public lookup --------
    Route::get('/verifier-demande',  [CandidatLookupController::class, 'showStatusForm'])->name('concours.public.status.form');
    Route::post('/verifier-demande', [CandidatLookupController::class, 'status'])
        ->middleware('throttle:30,1')->name('concours.public.status');

    Route::get('/recuperer-dossier', [CandidatLookupController::class, 'showModifyForm'])->name('concours.public.lookup.form');
    Route::post('/recuperer-dossier',[CandidatLookupController::class, 'submitLookup'])
        ->middleware('throttle:10,1')->name('concours.public.lookup.submit');

    // Token-gated modification wizard. Mirrors the inscription wizard:
    // 5 steps, per-doc AJAX staging, session-persisted draft. Candidat
    // identity comes from the session (set by submitLookup), not the URL.
    Route::get('/modifier-dossier/{token}', [CandidatModificationWizardController::class, 'entry'])
        ->where('token', '[A-Z0-9]{26}')
        ->name('concours.public.modify.form');  // legacy name kept for back-compat

    Route::post('/modifier-dossier/{token}/reset', [CandidatModificationWizardController::class, 'reset'])
        ->where('token', '[A-Z0-9]{26}')
        ->name('concours.public.modify.wizard.reset');

    Route::post('/modifier-dossier/{token}/documents/stage', [CandidatModificationWizardController::class, 'stageDocument'])
        ->where('token', '[A-Z0-9]{26}')
        ->middleware('throttle:60,1')
        ->name('concours.public.modify.wizard.stage');
    Route::delete('/modifier-dossier/{token}/documents/stage/{code}', [CandidatModificationWizardController::class, 'unstageDocument'])
        ->where(['token' => '[A-Z0-9]{26}', 'code' => '[A-Za-z0-9_\-]{1,60}'])
        ->name('concours.public.modify.wizard.unstage');

    Route::get('/modifier-dossier/{token}/{step}', [CandidatModificationWizardController::class, 'show'])
        ->where(['token' => '[A-Z0-9]{26}', 'step' => 'identite|contact|bac|choix|documents'])
        ->name('concours.public.modify.wizard.show');
    Route::post('/modifier-dossier/{token}/{step}', [CandidatModificationWizardController::class, 'submit'])
        ->where(['token' => '[A-Z0-9]{26}', 'step' => 'identite|contact|bac|choix|documents'])
        ->middleware('throttle:30,1')
        ->name('concours.public.modify.wizard.submit');
    Route::post('/modifier-dossier/{token}/{step}/back', [CandidatModificationWizardController::class, 'back'])
        ->where(['token' => '[A-Z0-9]{26}', 'step' => 'identite|contact|bac|choix|documents'])
        ->name('concours.public.modify.wizard.back');

    // Legacy alias: the old single-page submit URL — bounce it at the
    // wizard entry so existing bookmarks survive.
    Route::post('/modifier-dossier/{token}', fn (string $token) => redirect()->route('concours.public.modify.wizard.entry', ['token' => $token]))
        ->where('token', '[A-Z0-9]{26}')
        ->name('concours.public.modify.submit');

    // Convenience alias for the entry redirect (used by the controller +
    // tests).
    Route::get('/modifier-dossier-entree/{token}', [CandidatModificationWizardController::class, 'entry'])
        ->where('token', '[A-Z0-9]{26}')
        ->name('concours.public.modify.wizard.entry');

    Route::get('/modifier-dossier-succes/{matricule}', [CandidatLookupController::class, 'editSuccess'])
        ->where('matricule', 'CUK-(?:[A-Z0-9]{12}|[0-9]{4}-[0-9]{5})')
        ->name('concours.public.modify.success');

    // -------- eBilling callback (no CSRF, server-to-server) --------
    Route::post('/payment/ebilling/callback', PaymentCallbackController::class)
        ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class])
        ->name('concours.payment.callback');

    // -------- Candidat-driven payment flow --------
    // POST: start (creates invoice + auto-redirects to eBilling portal)
    // GET : return URL after coming back from eBilling
    Route::post('/candidat/{matricule}/payer', [PaymentController::class, 'start'])
        ->where('matricule', 'CUK-(?:[A-Z0-9]{12}|[0-9]{4}-[0-9]{5})')
        ->middleware('throttle:6,1')
        ->name('concours.public.payment.start');
    Route::get('/candidat/{matricule}/payment/retour', [PaymentController::class, 'return'])
        ->where('matricule', 'CUK-(?:[A-Z0-9]{12}|[0-9]{4}-[0-9]{5})')
        ->name('concours.public.payment.return');

    // -------- Public candidate dashboard + résultats publics --------
    Route::get('/candidat/{matricule}', [CandidatDashboardController::class, 'dashboard'])
        ->where('matricule', 'CUK-(?:[A-Z0-9]{12}|[0-9]{4}-[0-9]{5})')
        ->name('concours.public.candidat.dashboard');

    // PDF downloads — two-step flow.
    //   GET  → show the identity-gate form (email + tel + reCAPTCHA).
    //   POST → verify identity, then stream the PDF.
    // GET keeps the historical route name `…candidat.pdf` so deep links
    // from the dashboard / status pages continue to land on the gate page.
    Route::get('/candidat/{matricule}/pdf/{document}', [CandidatDashboardController::class, 'showPdfGate'])
        ->where(['matricule' => 'CUK-(?:[A-Z0-9]{12}|[0-9]{4}-[0-9]{5})', 'document' => 'fiche|emploi-du-temps'])
        ->middleware('throttle:30,1')
        ->name('concours.public.candidat.pdf');
    Route::post('/candidat/{matricule}/pdf/{document}', [CandidatDashboardController::class, 'streamPdf'])
        ->where(['matricule' => 'CUK-(?:[A-Z0-9]{12}|[0-9]{4}-[0-9]{5})', 'document' => 'fiche|emploi-du-temps'])
        ->middleware(['recaptcha', 'throttle:10,1'])
        ->name('concours.public.candidat.pdf.stream');

    Route::get('/resultats', [CandidatDashboardController::class, 'results'])
        ->name('concours.public.results');
    Route::get('/resultats/{sessionCode}/telecharger', [CandidatDashboardController::class, 'resultsDownload'])
        ->where('sessionCode', '[A-Za-z0-9\-]+')
        ->name('concours.public.results.download');

    // -------- Étudiant space (auth + 2FA + role) --------
    // Active role must be `etudiant` — the EnsureActiveRole middleware
    // already handles routing single-role users straight here; multi-role
    // users get the picker and the picker links here when they pick étudiant.
    Route::middleware(['auth', 'twofactor', 'active.role'])
        ->get('/espace-etudiant', [EtudiantSpaceController::class, 'show'])
        ->name('etudiant.space');

    // -------- Admin HTML pages (auth + 2FA) --------
    Route::middleware(['auth', 'twofactor', 'active.role'])
        ->prefix('admin/concours')
        ->name('admin.pages.concours.')
        ->group(function (): void {
            Route::get('/candidats',                       [CandidatPageController::class, 'index'])->name('candidats.index');
            Route::post('/candidats/data',                 [CandidatPageController::class, 'data'])->name('candidats.data');
            Route::get('/candidats/export.{format}',       [CandidatPageController::class, 'export'])
                ->where('format', 'xlsx|csv|pdf')->name('candidats.export');
            Route::get('/candidats/{candidat}',            [CandidatPageController::class, 'show'])->name('candidats.show');
            Route::get('/candidats/{candidat}/edit',       [CandidatPageController::class, 'edit'])->name('candidats.edit');

            Route::get('/epreuves',             [EpreuvePageController::class, 'index'])->name('epreuves.index');
            Route::post('/epreuves/data',       [EpreuvePageController::class, 'data'])->name('epreuves.data');

            Route::get('/notes',                [NotePageController::class, 'picker'])->name('notes.picker');
            Route::get('/notes/{epreuve}',      [NotePageController::class, 'grid'])->name('notes.grid');

            Route::get('/selection',            [SelectionPageController::class, 'wizard'])->name('selection.wizard');

            Route::get('/sessions',                       [SessionPageController::class, 'index'])->name('sessions.index');
            Route::post('/sessions',                      [SessionPageController::class, 'store'])->name('sessions.store');
            Route::post('/sessions/switch',               [SessionPageController::class, 'switchActive'])->name('sessions.switch');
            Route::put('/sessions/{session}',             [SessionPageController::class, 'update'])->name('sessions.update');
            Route::post('/sessions/{session}/activate',   [SessionPageController::class, 'activate'])->name('sessions.activate');

            Route::get('/planning',                       [PlanningPageController::class, 'index'])->name('planning.index');
            Route::post('/planning',                      [PlanningPageController::class, 'store'])->name('planning.store');
            Route::post('/planning/inherit',              [PlanningPageController::class, 'inherit'])->name('planning.inherit');
            Route::delete('/planning/{planning}',         [PlanningPageController::class, 'destroy'])->name('planning.destroy');

            // Payments — read-only DG/DE/super-admin view of every Payment row,
            // with a per-row detail page that links to the candidat dossier.
            Route::get('/payments',                       [PaymentPageController::class, 'index'])->name('payments.index');
            Route::post('/payments/data',                 [PaymentPageController::class, 'data'])->name('payments.data');
            Route::get('/payments/{payment}',             [PaymentPageController::class, 'show'])
                ->where('payment', '[0-9a-fA-F-]{36}')
                ->name('payments.show');

            // Centres CRUD — DG / DE / super-admin
            Route::get('/centres',                        [CentrePageController::class, 'index'])->name('centres.index');
            Route::post('/centres',                       [CentrePageController::class, 'store'])->name('centres.store');
            Route::patch('/centres/{centre}',             [CentrePageController::class, 'update'])->name('centres.update');
            Route::post('/centres/{centre}/toggle',       [CentrePageController::class, 'toggleActive'])->name('centres.toggle');

            // Doc-requis × sections matrix (which docs apply to which sections)
            Route::get('/document-requis-sections',         [DocumentRequisSectionMatrixPageController::class, 'index'])
                ->name('document_requis_sections.index');
            Route::post('/document-requis-sections/toggle', [DocumentRequisSectionMatrixPageController::class, 'toggle'])
                ->middleware('throttle:60,1')
                ->name('document_requis_sections.toggle');

            // Chef-centre assignments — per-session matrix
            Route::get('/chef-centres',                              [ChefCentreAssignmentPageController::class, 'index'])->name('chef_centres.index');
            Route::post('/chef-centres/assign',                      [ChefCentreAssignmentPageController::class, 'assign'])->name('chef_centres.assign');
            Route::post('/chef-centres/create-and-assign',           [ChefCentreAssignmentPageController::class, 'createAndAssign'])->name('chef_centres.create_and_assign');
            Route::post('/chef-centres/{assignment}/principal',      [ChefCentreAssignmentPageController::class, 'togglePrincipal'])->name('chef_centres.toggle_principal');
            Route::delete('/chef-centres/{assignment}',              [ChefCentreAssignmentPageController::class, 'destroy'])->name('chef_centres.destroy');
        });

    // -------- Admin JSON API (auth + 2FA + RBAC) --------
    Route::middleware(['auth', 'twofactor', 'active.role'])
        ->prefix('api/admin/concours')
        ->name('admin.concours.')
        ->group(function (): void {
            // Candidats (stage 5A)
            Route::get('/candidats',             [CandidatController::class, 'index'])->name('candidats.index');
            Route::get('/candidats/{candidat}',  [CandidatController::class, 'show'])->name('candidats.show');
            Route::put('/candidats/{candidat}',  [CandidatController::class, 'update'])->name('candidats.update');
            Route::post('/candidats/{candidat}/decide', [CandidatController::class, 'decide'])->name('candidats.decide');

            // Per-document review workflow
            Route::get('/candidats/{candidat}/photo',
                [CandidatDocumentController::class, 'photo'])
                ->name('candidats.photo');
            // Auto-generated PDFs (fiche / emploi-du-temps) — admin-only,
            // streams inline so the doc-preview modal can iframe it. Bypasses
            // the public identity-gate (email + tel + reCAPTCHA) since the
            // admin already satisfies a stronger gate via auth + 2FA + RBAC.
            Route::get('/candidats/{candidat}/pdf/{document}',
                [CandidatDocumentController::class, 'pdf'])
                ->where('document', 'fiche|emploi-du-temps')
                ->name('candidats.pdf');
            Route::get('/candidats/{candidat}/documents/{doc}/preview',
                [CandidatDocumentController::class, 'preview'])
                ->name('candidats.documents.preview');
            Route::post('/candidats/{candidat}/documents/{doc}/review',
                [CandidatDocumentController::class, 'review'])
                ->name('candidats.documents.review');
            Route::post('/candidats/{candidat}/documents/{doc}/replace',
                [CandidatDocumentController::class, 'replace'])
                ->middleware('throttle:30,1')
                ->name('candidats.documents.replace');
            Route::post('/candidats/{candidat}/documents/bulk-validate',
                [CandidatDocumentController::class, 'bulkValidate'])
                ->name('candidats.documents.bulkValidate');

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
