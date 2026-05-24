<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\AcademicStructure\Models\Section;
use Modules\Concours\Models\Candidat;
use Modules\Concours\Models\Centre;
use Modules\Concours\Models\ConcoursSession;
use Modules\Referentiels\Models\Nationalite;
use Modules\Referentiels\Models\SerieBac;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('local');
    $this->seed(Modules\Referentiels\Database\Seeders\ReferentielsDatabaseSeeder::class);
    $this->seed(Modules\AcademicStructure\Database\Seeders\AcademicStructureDatabaseSeeder::class);
    $this->seed(Modules\Concours\Database\Seeders\CentresSeeder::class);
    $this->seed(Modules\Concours\Database\Seeders\ConcoursSessionsSeeder::class);

    // Open inscriptions for "today" so the form lets us through.
    ConcoursSession::active()?->forceFill([
        'date_ouverture_inscriptions' => now()->subDays(2),
        'date_fermeture_inscriptions' => now()->addDays(30),
        'statut'                      => 'inscriptions_ouvertes',
    ])->save();
});

function registrationPayload(): array
{
    return [
        'centre_id'                  => Centre::query()->first()->id,
        'nom'                        => 'NDONG',
        'prenom'                     => 'Alex',
        'date_naissance'             => '2005-04-10',
        'lieu_naissance'             => 'Libreville',
        'sexe'                       => 'M',
        'nationalite_id'             => Nationalite::query()->where('code_iso', 'GA')->first()->id,
        'email'                      => 'alex.ndong@example.test',
        'telephone'                  => '062345678',
        'deja_bac'                   => '1',
        'annee_bac'                  => (string) (date('Y') - 1),
        'serie_bac_id'               => SerieBac::query()->where('code', 'D')->first()->id,
        'etablissement_frequente'    => 'Lycée Léon MBA',
        'section_premier_choix_id'   => Section::query()->where('code', 'IC')->first()->id,
        'photo'                      => UploadedFile::fake()->image('photo.jpg', 300, 400)->size(200),
        'documents'                  => [
            'acte'    => UploadedFile::fake()->create('acte.pdf', 800, 'application/pdf'),
            'colebac' => UploadedFile::fake()->create('colebac.pdf', 800, 'application/pdf'),
            'rnbac'   => UploadedFile::fake()->create('rnbac.pdf', 800, 'application/pdf'),
        ],
    ];
}

it('renders the public registration form when inscriptions are open', function (): void {
    $this->get('/inscription')->assertOk()->assertSee('Inscription au concours');
});

it('redirects to the closed page when no session is open', function (): void {
    ConcoursSession::query()->update(['est_active' => false]);
    $this->get('/inscription')->assertRedirect(route('concours.inscriptions.fermees'));
});

it('registers a candidate, creates documents and lands on the success page', function (): void {
    $response = $this->post('/inscription', registrationPayload());

    $candidat = Candidat::query()->where('email', 'alex.ndong@example.test')->first();
    expect($candidat)->not->toBeNull()
        ->and($candidat->statut)->toBe(Candidat::STATUS_NON)
        ->and($candidat->matricule_public)->toStartWith('CUK-')
        ->and($candidat->documents()->count())->toBe(3);

    $response->assertRedirect(route('concours.inscription.success', ['matricule' => $candidat->matricule_public]));
});

it('rejects a duplicate email within the same session', function (): void {
    $this->post('/inscription', registrationPayload());

    $second = registrationPayload();
    $second['telephone'] = '062999000'; // different phone, same email
    $this->post('/inscription', $second)->assertSessionHasErrors('email');
});

it('requires annee_bac when deja_bac=true', function (): void {
    $payload = registrationPayload();
    $payload['deja_bac'] = '1';
    $payload['annee_bac'] = '';
    $this->post('/inscription', $payload)->assertSessionHasErrors('annee_bac');
});

it('locates a candidate via the public status endpoint', function (): void {
    $this->post('/inscription', registrationPayload());
    $matricule = Candidat::query()->where('email', 'alex.ndong@example.test')->first()->matricule_public;

    $this->post('/verifier-demande', ['matricule' => $matricule])
        ->assertOk()
        ->assertSee($matricule);
});
