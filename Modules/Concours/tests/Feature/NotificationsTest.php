<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Modules\AcademicStructure\Models\Section;
use Modules\Concours\DTOs\ConfirmSelectionDto;
use Modules\Concours\DTOs\ValidationDecisionDto;
use Modules\Concours\Models\Candidat;
use Modules\Concours\Models\ConcoursSession;
use Modules\Concours\Models\Payment;
use Modules\Concours\Notifications\DossierAcceptedNotification;
use Modules\Concours\Notifications\DossierRejectedNotification;
use Modules\Concours\Notifications\PaymentConfirmedNotification;
use Modules\Concours\Notifications\ResultsPublishedNotification;
use Modules\Concours\Services\CandidatValidationService;
use Modules\Concours\Services\Ebilling\EbillingService;
use Modules\Concours\Services\SelectionService;
use Modules\Referentiels\Models\Nationalite;
use Modules\Referentiels\Models\SerieBac;
use Modules\UserManagement\Models\Role;
use Modules\UserManagement\Models\User;
use Modules\UserManagement\Notifications\WelcomeAdmisNotification;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Notification::fake();

    $this->seed(Modules\Referentiels\Database\Seeders\ReferentielsDatabaseSeeder::class);
    $this->seed(Modules\AcademicStructure\Database\Seeders\AcademicStructureDatabaseSeeder::class);
    $this->seed(Modules\UserManagement\Database\Seeders\UserManagementDatabaseSeeder::class);
    $this->seed(Modules\Parametrage\Database\Seeders\SettingsSeeder::class);
    $this->seed(Modules\Concours\Database\Seeders\CentresSeeder::class);
    $this->seed(Modules\Concours\Database\Seeders\ConcoursSessionsSeeder::class);
});

function makeCandidat(string $status = 'non'): Candidat
{
    $session = ConcoursSession::active();
    $centre  = Modules\Concours\Models\Centre::query()->first();
    $section = Section::query()->first();
    $bac     = SerieBac::query()->first();
    $nat     = Nationalite::query()->first();

    return Candidat::query()->create([
        'concours_session_id'      => $session->id,
        'centre_id'                => $centre->id,
        'nom' => 'TEST', 'prenom' => 'User',
        'date_naissance' => '2000-01-01', 'lieu_naissance' => 'X', 'sexe' => 'M',
        'nationalite_id' => $nat->id,
        'email' => 'user-' . \Illuminate\Support\Str::random(6) . '@test.example',
        'telephone' => '06' . random_int(1000000, 9999999),
        'deja_bac' => true, 'annee_bac' => 2024,
        'serie_bac_id' => $bac->id, 'etablissement_frequente' => 'X',
        'section_premier_choix_id' => $section->id,
        'statut' => $status,
        'matricule_public' => 'CUK-' . strtoupper(\Illuminate\Support\Str::random(12)),
    ]);
}

it('sends DossierAcceptedNotification on accept', function (): void {
    $c = makeCandidat();
    $admin = User::factory()->create();

    app(CandidatValidationService::class)->decide(new ValidationDecisionDto(
        candidatId: $c->id, userId: $admin->id, decision: 'accept',
    ));

    Notification::assertSentOnDemand(DossierAcceptedNotification::class,
        fn ($n, $channels, $notifiable) => $notifiable->routes['mail'] === $c->email
    );
});

it('sends DossierRejectedNotification on reject with motifs', function (): void {
    $c = makeCandidat();
    $admin = User::factory()->create();

    app(CandidatValidationService::class)->decide(new ValidationDecisionDto(
        candidatId: $c->id, userId: $admin->id, decision: 'reject',
        motifs: ['Acte de naissance manquant'],
    ));

    Notification::assertSentOnDemand(DossierRejectedNotification::class,
        fn ($n) => $n->motifs === ['Acte de naissance manquant']
    );
});

it('sends PaymentConfirmedNotification after eBilling markPaid', function (): void {
    $c = makeCandidat('oui');
    $payment = Payment::query()->create([
        'candidat_id'         => $c->id,
        'concours_session_id' => $c->concours_session_id,
        'amount'              => 10300, 'currency' => 'FCFA',
        'external_reference'  => 'REF-TEST-' . random_int(1000, 9999),
        'status'              => Payment::STATUS_PENDING,
    ]);

    app(EbillingService::class)->markPaid($payment, ['status' => 'PAID'], '127.0.0.1');

    Notification::assertSentOnDemand(PaymentConfirmedNotification::class);
});

it('sends ResultsPublishedNotification AND WelcomeAdmisNotification on selection confirm', function (): void {
    $c = makeCandidat('valid');
    $c->forceFill(['moyenne' => 17.5, 'rang' => 1])->save();

    $section = Section::query()->where('code', $c->premierChoix?->code ?? 'IC')->first()
        ?? Section::query()->first();
    $admin = User::factory()->create();
    $admin->roles()->sync([Role::query()->where('code', 'super-admin')->value('id')]);

    app(SelectionService::class)->confirm(new ConfirmSelectionDto(
        concoursSessionId: $c->concours_session_id,
        publishedByUserId: $admin->id,
        admis: [['candidat_id' => $c->id, 'orientation_section_id' => $section->id]],
    ));

    Notification::assertSentOnDemand(ResultsPublishedNotification::class);

    // The newly-created User account receives the WelcomeAdmis notification.
    $newUser = User::query()->where('email', $c->email)->first();
    expect($newUser)->not->toBeNull();
    Notification::assertSentTo($newUser, WelcomeAdmisNotification::class);
});
