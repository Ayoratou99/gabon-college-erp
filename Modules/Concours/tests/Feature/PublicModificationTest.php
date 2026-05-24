<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\AcademicStructure\Models\Section;
use Modules\Concours\Models\Candidat;
use Modules\Concours\Models\CandidatModification;
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
});

function rejectedCandidat(): Candidat
{
    $session = ConcoursSession::active();
    $centre  = Modules\Concours\Models\Centre::query()->first();
    $section = Section::query()->first();
    $bac     = SerieBac::query()->first();
    $nat     = Nationalite::query()->first();

    return Candidat::query()->create([
        'concours_session_id'      => $session->id,
        'centre_id'                => $centre->id,
        'nom' => 'NDONG', 'prenom' => 'Alex',
        'date_naissance' => '2005-04-10', 'lieu_naissance' => 'Libreville', 'sexe' => 'M',
        'nationalite_id' => $nat->id,
        'email' => 'alex@test.example', 'telephone' => '062345678',
        'deja_bac' => true, 'annee_bac' => 2024,
        'serie_bac_id' => $bac->id, 'etablissement_frequente' => 'Lycée X',
        'section_premier_choix_id' => $section->id,
        'statut' => Candidat::STATUS_REJETE,
        'matricule_public' => 'CUK-' . strtoupper(Str::random(12)),
    ]);
}

function grantToken(Candidat $c): string
{
    $token = (string) Str::ulid();
    $keys = config('concours.public_lookup.session_keys');
    session([
        $keys['modification_token']    => $token,
        $keys['modification_candidat'] => $c->id,
        $keys['modification_expires']  => now()->addHour()->timestamp,
    ]);
    return $token;
}

it('redirects when the token is missing or expired', function (): void {
    $token = (string) Str::ulid();
    $this->get("/modifier-dossier/{$token}")->assertRedirect(route('concours.public.lookup.form'));
});

it('refuses a submission whose URL token does not match the session token', function (): void {
    $c = rejectedCandidat();
    $valid = grantToken($c);
    $wrong = (string) Str::ulid();

    $this->post("/modifier-dossier/{$wrong}", validPayload($c))
        ->assertForbidden();
});

it('lookup → edit → resubmit resets the status to "non" and audits each change', function (): void {
    $c = rejectedCandidat();
    $token = grantToken($c);

    $payload = validPayload($c);
    $payload['nom']       = 'NDONG-CHANGED';
    $payload['telephone'] = '062999000';

    $this->post("/modifier-dossier/{$token}", $payload)
        ->assertRedirect(route('concours.public.modify.success', $c->matricule_public));

    $c->refresh();
    expect($c->statut)->toBe(Candidat::STATUS_NON)
        ->and($c->nom)->toBe('NDONG-CHANGED')
        ->and($c->telephone)->toBe('062999000');

    $log = CandidatModification::query()
        ->where('candidat_id', $c->id)
        ->where('channel', 'public')
        ->get();

    expect($log->pluck('field')->all())->toContain('nom', 'telephone');
});

it('rejects an email already used by another candidat in the same session', function (): void {
    $other = rejectedCandidat();   // email = alex@test.example
    $other->forceFill(['statut' => Candidat::STATUS_OUI])->save();

    $c = rejectedCandidat();       // 2nd candidat — must get a fresh row
    $c->forceFill(['email' => 'beta@test.example', 'telephone' => '066000111'])->save();

    $token = grantToken($c);

    $payload = validPayload($c);
    $payload['email'] = 'alex@test.example'; // taken by $other

    $this->post("/modifier-dossier/{$token}", $payload)
        ->assertSessionHasErrors('email');
});

it('invalidates the token after a successful submit', function (): void {
    $c = rejectedCandidat();
    $token = grantToken($c);

    $this->post("/modifier-dossier/{$token}", validPayload($c))
        ->assertRedirect(route('concours.public.modify.success', $c->matricule_public));

    // The same token must no longer let through a second submit.
    $this->post("/modifier-dossier/{$token}", validPayload($c))
        ->assertForbidden();
});

function validPayload(Candidat $c): array
{
    return [
        'nom' => $c->nom, 'prenom' => $c->prenom,
        'date_naissance' => $c->date_naissance->format('Y-m-d'),
        'lieu_naissance' => $c->lieu_naissance, 'sexe' => $c->sexe,
        'nationalite_id' => $c->nationalite_id,
        'email' => $c->email, 'telephone' => $c->telephone,
        'deja_bac' => '1', 'annee_bac' => '2024',
        'serie_bac_id' => $c->serie_bac_id,
        'etablissement_frequente' => $c->etablissement_frequente,
        'section_premier_choix_id' => $c->section_premier_choix_id,
        'centre_id' => $c->centre_id,
    ];
}
