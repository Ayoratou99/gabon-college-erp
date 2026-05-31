<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\AcademicStructure\Models\Section;
use Modules\Concours\Models\Candidat;
use Modules\Concours\Models\Centre;
use Modules\Concours\Models\ConcoursSession;
use Modules\Referentiels\Models\Nationalite;
use Modules\Referentiels\Models\SerieBac;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(Modules\Referentiels\Database\Seeders\ReferentielsDatabaseSeeder::class);
    $this->seed(Modules\AcademicStructure\Database\Seeders\AcademicStructureDatabaseSeeder::class);
    $this->seed(Modules\Concours\Database\Seeders\CentresSeeder::class);
    $this->seed(Modules\Concours\Database\Seeders\ConcoursSessionsSeeder::class);
    $this->seed(Modules\Parametrage\Database\Seeders\SettingsSeeder::class);

    ConcoursSession::active()?->forceFill([
        'date_ouverture_inscriptions' => now()->subDay(),
        'date_fermeture_inscriptions' => now()->addMonth(),
    ])->save();
});

function makeRejectedCandidat(): Candidat
{
    $session = ConcoursSession::active();
    return Candidat::query()->create([
        'concours_session_id'      => $session->id,
        'centre_id'                => Centre::query()->first()->id,
        'nom'                      => 'DUPONT', 'prenom' => 'Jean',
        'date_naissance'           => '2000-01-15',
        'lieu_naissance'           => 'Libreville',
        'sexe'                     => 'M',
        'nationalite_id'           => Nationalite::query()->first()->id,
        'email'                    => 'jean.modify@example.test',
        'telephone'                => '+241 06 11 22 33',
        'deja_bac'                 => true, 'annee_bac' => 2024,
        'serie_bac_id'             => SerieBac::query()->first()->id,
        'etablissement_frequente'  => 'Lycée Test',
        'section_premier_choix_id' => Section::query()->first()->id,
        'statut'                   => Candidat::STATUS_REJETE,
        'matricule_public'         => 'CUK-' . now()->format('Y') . '-00001',
    ]);
}

function plantToken(Candidat $candidat): string
{
    $keys  = (array) config('concours.public_lookup.session_keys');
    $token = strtoupper((string) Str::ulid()->toBase32());
    session()->put($keys['modification_token'],    $token);
    session()->put($keys['modification_candidat'], (string) $candidat->getKey());
    session()->put($keys['modification_expires'],  now()->addHour()->timestamp);
    return $token;
}

it('entry redirects to the first step', function (): void {
    $candidat = makeRejectedCandidat();
    $token    = plantToken($candidat);

    $this->get('/modifier-dossier/' . $token)
        ->assertRedirect('/modifier-dossier/' . $token . '/identite');
});

it('rejects a bad token by bouncing to the lookup form', function (): void {
    $this->get('/modifier-dossier/INVALIDTOKEN12345ABCDEFGHIJ/identite')
        ->assertRedirect('/recuperer-dossier');
});

it('step 1 pre-fills from the existing candidat row', function (): void {
    $candidat = makeRejectedCandidat();
    $token    = plantToken($candidat);

    $this->get('/modifier-dossier/' . $token . '/identite')
        ->assertOk()
        ->assertSee('Modifier mon dossier')
        ->assertSee('value="DUPONT"', escape: false)
        ->assertSee('value="Jean"', escape: false);
});

it('submitting a step merges into the modify draft and advances', function (): void {
    $candidat = makeRejectedCandidat();
    $token    = plantToken($candidat);

    $this->post('/modifier-dossier/' . $token . '/identite', [
        'nom'            => 'NOUVEAU',
        'prenom'         => $candidat->prenom,
        'date_naissance' => $candidat->date_naissance->format('Y-m-d'),
        'lieu_naissance' => $candidat->lieu_naissance,
        'sexe'           => $candidat->sexe,
        'nationalite_id' => $candidat->nationalite_id,
    ])->assertRedirect('/modifier-dossier/' . $token . '/contact');

    // On the next step, the draft value should still be there (not yet
    // persisted to the candidat row — only the final submit does that).
    expect($candidat->fresh()->nom)->toBe('DUPONT');
});

it('rejects when inscriptions are closed since the token was issued', function (): void {
    $candidat = makeRejectedCandidat();
    $token    = plantToken($candidat);

    ConcoursSession::active()?->forceFill([
        'date_fermeture_inscriptions' => now()->subDay(),
    ])->save();

    $this->get('/modifier-dossier/' . $token . '/identite')
        ->assertRedirect(route('concours.public.status.form'));
});

it('uses the modify-specific routes for stage / unstage / submit', function (): void {
    $candidat = makeRejectedCandidat();
    $token    = plantToken($candidat);

    $this->get('/modifier-dossier/' . $token . '/documents')
        // The documents step JS should be pointed at the modify-specific
        // stage URL, not the inscription one.
        ->assertSee('/modifier-dossier/' . $token . '/documents/stage', escape: false)
        ->assertSee('Renvoyer mon dossier corrigé');
});
