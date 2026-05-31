<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Concours\Models\Candidat;
use Modules\Concours\Models\Payment;
use Modules\Concours\Services\Ebilling\PaymentReferenceCipher;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(Modules\Referentiels\Database\Seeders\ReferentielsDatabaseSeeder::class);
    $this->seed(Modules\AcademicStructure\Database\Seeders\AcademicStructureDatabaseSeeder::class);
    $this->seed(Modules\Concours\Database\Seeders\CentresSeeder::class);
    $this->seed(Modules\Concours\Database\Seeders\ConcoursSessionsSeeder::class);
    $this->seed(Modules\Parametrage\Database\Seeders\SettingsSeeder::class);

    // eBilling no longer signs the callback body — the only authenticity proof
    // is that the `external_reference` decrypts cleanly under our AES-256-GCM
    // key. Set a deterministic 32-byte key for the test suite.
    config()->set(
        'concours.ebilling.reference_key',
        'base64:' . base64_encode(str_repeat("\x42", 32)),
    );
});

function makeCandidatWithPayment(): array
{
    $session     = Modules\Concours\Models\ConcoursSession::active();
    $centre      = Modules\Concours\Models\Centre::query()->first();
    $section     = Modules\AcademicStructure\Models\Section::query()->first();
    $bac         = Modules\Referentiels\Models\SerieBac::query()->first();
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
        'external_reference'  => 'placeholder',
        'status'              => Payment::STATUS_PENDING,
    ]);

    // Encrypt the reference *after* creation so we have the real payment UUID
    // bound in the payload — exactly the way the production controller does.
    $cipher    = app(PaymentReferenceCipher::class);
    $reference = $cipher->encode((string) $payment->id, (string) $candidat->id);
    $payment->forceFill(['external_reference' => $reference])->save();

    return [$candidat, $payment, $reference];
}

it('rejects a callback with no reference at all', function (): void {
    $this->postJson('/payment/ebilling/callback', [])
        ->assertStatus(400);
});

it('rejects a callback whose reference cannot be decrypted', function (): void {
    [, $payment] = makeCandidatWithPayment();

    // Garbled base64url that does not decrypt under our key.
    $this->postJson('/payment/ebilling/callback', [
        'reference' => 'this-is-not-a-valid-ciphertext',
        'status'    => 'PAID',
    ])->assertStatus(400);

    // Nothing was written — the row stays exactly as it was.
    expect($payment->refresh()->status)->toBe(Payment::STATUS_PENDING)
        ->and($payment->signature_verified)->toBeFalse();
});

it('returns 404 if the reference decrypts but the row was deleted', function (): void {
    [, $payment, $reference] = makeCandidatWithPayment();
    $payment->forceDelete();

    $this->postJson('/payment/ebilling/callback', [
        'reference' => $reference,
        'status'    => 'PAID',
    ])->assertStatus(404);
});

it('rejects a reference whose embedded candidat_id does not match the row', function (): void {
    [, $payment] = makeCandidatWithPayment();

    // Forge a reference that decrypts cleanly (right key, right structure)
    // but binds a *different* candidat_id than what the DB row carries.
    $cipher        = app(PaymentReferenceCipher::class);
    $forged        = $cipher->encode((string) $payment->id, '00000000-0000-7000-8000-000000000000');
    $payment->forceFill(['external_reference' => $forged])->save();

    $this->postJson('/payment/ebilling/callback', [
        'reference' => $forged,
        'status'    => 'PAID',
    ])->assertStatus(400);

    expect($payment->refresh()->status)->toBe(Payment::STATUS_PENDING);
});

it('accepts a valid encrypted reference and marks the candidate paid', function (): void {
    [$candidat, $payment, $reference] = makeCandidatWithPayment();

    $this->postJson('/payment/ebilling/callback', [
        'reference' => $reference,
        'status'    => 'PAID',
    ])->assertOk();

    expect($payment->refresh()->status)->toBe(Payment::STATUS_PAID)
        ->and($payment->signature_verified)->toBeTrue()
        ->and($candidat->refresh()->statut)->toBe(Candidat::STATUS_VALID);
});

it('is idempotent — a second callback returns already_paid without re-flipping', function (): void {
    [, $payment, $reference] = makeCandidatWithPayment();

    $this->postJson('/payment/ebilling/callback', ['reference' => $reference, 'status' => 'PAID'])
        ->assertOk();
    $firstPaidAt = $payment->refresh()->paid_at;

    $this->postJson('/payment/ebilling/callback', ['reference' => $reference, 'status' => 'PAID'])
        ->assertOk()
        ->assertJson(['status' => 'already_paid']);

    // The original paid_at timestamp survives the second hit.
    expect($payment->refresh()->paid_at->toIso8601String())
        ->toBe($firstPaidAt->toIso8601String());
});

it('also accepts the field under external_reference (alt eBilling spelling)', function (): void {
    [, , $reference] = makeCandidatWithPayment();

    $this->postJson('/payment/ebilling/callback', [
        'external_reference' => $reference,
        'status'             => 'PAID',
    ])->assertOk();
});
