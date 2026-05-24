<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Concours\Models\Candidat;
use Modules\Concours\Models\Payment;
use Modules\Parametrage\Services\SettingsService;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(Modules\Referentiels\Database\Seeders\ReferentielsDatabaseSeeder::class);
    $this->seed(Modules\AcademicStructure\Database\Seeders\AcademicStructureDatabaseSeeder::class);
    $this->seed(Modules\Concours\Database\Seeders\CentresSeeder::class);
    $this->seed(Modules\Concours\Database\Seeders\ConcoursSessionsSeeder::class);
    $this->seed(Modules\Parametrage\Database\Seeders\SettingsSeeder::class);

    // Set HMAC secret so verifyCallback can check signatures.
    app(SettingsService::class)->set('ebilling.hmac_secret', 'test-hmac-secret-32-chars-long-xx');
});

function makeCandidatWithPayment(): array
{
    $session = Modules\Concours\Models\ConcoursSession::active();
    $centre = Modules\Concours\Models\Centre::query()->first();
    $section = Modules\AcademicStructure\Models\Section::query()->first();
    $bac = Modules\Referentiels\Models\SerieBac::query()->first();
    $nationalite = Modules\Referentiels\Models\Nationalite::query()->first();

    $candidat = Candidat::query()->create([
        'concours_session_id'      => $session->id,
        'centre_id'                => $centre->id,
        'nom' => 'TEST', 'prenom' => 'User',
        'date_naissance' => '2000-01-01', 'lieu_naissance' => 'Libreville', 'sexe' => 'M',
        'nationalite_id' => $nationalite->id,
        'email' => 'test@example.test', 'telephone' => '066000111',
        'deja_bac' => true, 'annee_bac' => 2024,
        'serie_bac_id' => $bac->id,
        'etablissement_frequente' => 'X',
        'section_premier_choix_id' => $section->id,
        'statut' => Candidat::STATUS_OUI,
        'matricule_public' => 'CUK-AAAAAAAAAAAA',
    ]);

    $payment = Payment::query()->create([
        'candidat_id'         => $candidat->id,
        'concours_session_id' => $session->id,
        'amount'              => 10300,
        'currency'            => 'FCFA',
        'external_reference'  => 'CUK-REF-TEST-001',
        'status'              => Payment::STATUS_PENDING,
    ]);

    return [$candidat, $payment];
}

it('returns 404 for an unknown reference', function (): void {
    $this->postJson('/payment/ebilling/callback', ['reference' => 'UNKNOWN'])
        ->assertStatus(404);
});

it('rejects a callback with a missing or wrong signature', function (): void {
    [, $payment] = makeCandidatWithPayment();

    $this->postJson('/payment/ebilling/callback',
        ['reference' => $payment->external_reference, 'status' => 'PAID'],
    )->assertStatus(400);

    expect($payment->refresh()->signature_verified)->toBeFalse()
        ->and($payment->status)->toBe(Payment::STATUS_PENDING);
});

it('accepts a callback with a valid HMAC and marks the candidate paid', function (): void {
    [$candidat, $payment] = makeCandidatWithPayment();

    $body = json_encode(['reference' => $payment->external_reference, 'status' => 'PAID']);
    $sig = hash_hmac('sha256', $body, 'test-hmac-secret-32-chars-long-xx');

    $this->call(
        method: 'POST',
        uri:    '/payment/ebilling/callback',
        server: [
            'HTTP_X-Ebilling-Signature' => $sig,
            'CONTENT_TYPE'              => 'application/json',
        ],
        content: $body,
    )->assertOk();

    expect($payment->refresh()->status)->toBe(Payment::STATUS_PAID)
        ->and($payment->signature_verified)->toBeTrue()
        ->and($candidat->refresh()->statut)->toBe(Candidat::STATUS_VALID);
});
