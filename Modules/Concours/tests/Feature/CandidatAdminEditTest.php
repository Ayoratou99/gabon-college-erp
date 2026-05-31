<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\AcademicStructure\Models\Section;
use Modules\Concours\Models\Candidat;
use Modules\Concours\Models\CandidatModification;
use Modules\Concours\Models\Centre;
use Modules\Concours\Models\ConcoursSession;
use Modules\Referentiels\Models\Nationalite;
use Modules\Referentiels\Models\SerieBac;
use Modules\UserManagement\Models\Role;
use Modules\UserManagement\Models\User;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(Modules\Referentiels\Database\Seeders\ReferentielsDatabaseSeeder::class);
    $this->seed(Modules\AcademicStructure\Database\Seeders\AcademicStructureDatabaseSeeder::class);
    $this->seed(Modules\Concours\Database\Seeders\CentresSeeder::class);
    $this->seed(Modules\Concours\Database\Seeders\ConcoursSessionsSeeder::class);
    $this->seed(Modules\UserManagement\Database\Seeders\RoleSeeder::class);
    $this->seed(Modules\Parametrage\Database\Seeders\SettingsSeeder::class);
});

function makeCandidat(): Candidat
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
        'email'                    => 'jean.dupont@example.test',
        'telephone'                => '+241 06 11 22 33',
        'deja_bac'                 => true,
        'annee_bac'                => 2024,
        'serie_bac_id'             => SerieBac::query()->first()->id,
        'etablissement_frequente'  => 'Lycée National',
        'section_premier_choix_id' => Section::query()->first()->id,
        'statut'                   => Candidat::STATUS_NON,
        'matricule_public'         => 'CUK-EDITTEST0001',
    ]);
}

function makeUserWithRole(string $roleCode): User
{
    $user = User::query()->create([
        'matricule'  => str_pad((string) random_int(1, 99999), 5, '0', STR_PAD_LEFT),
        'nom'        => 'Test',
        'prenom'     => ucfirst($roleCode),
        'email'      => $roleCode . '-' . random_int(1, 99999) . '@example.test',
        'password'   => bcrypt('test1234'),
        'is_active'  => true,
    ]);
    $role = Role::query()->where('code', $roleCode)->first();
    $user->roles()->attach($role);
    return $user->fresh();
}

it('lets DG edit a candidat and writes one audit row per changed field', function (): void {
    $dg       = makeUserWithRole('dg');
    $candidat = makeCandidat();

    $response = $this->actingAs($dg)->putJson(
        "/api/admin/concours/candidats/{$candidat->id}",
        [
            'nom'    => 'DUPONT-NEW',
            'prenom' => 'Jean-Marie',
            'reason' => 'Correction nom suite pièce d\'identité',
        ],
    );

    $response->assertOk()
        ->assertJsonStructure(['id', 'changed_fields', 'updated_at']);
    expect($response->json('changed_fields'))->toEqualCanonicalizing(['nom', 'prenom']);

    $candidat->refresh();
    expect($candidat->nom)->toBe('DUPONT-NEW')
        ->and($candidat->prenom)->toBe('Jean-Marie');

    $rows = CandidatModification::query()
        ->where('candidat_id', $candidat->id)
        ->where('channel', CandidatModification::CHANNEL_ADMIN)
        ->orderBy('field')->get();

    expect($rows)->toHaveCount(2);
    expect($rows[0]->field)->toBe('nom')
        ->and($rows[0]->old_value)->toBe('DUPONT')
        ->and($rows[0]->new_value)->toBe('DUPONT-NEW')
        ->and($rows[0]->reason)->toBe('Correction nom suite pièce d\'identité');
    expect($rows[1]->field)->toBe('prenom')
        ->and($rows[1]->old_value)->toBe('Jean')
        ->and($rows[1]->new_value)->toBe('Jean-Marie');
});

it('no-op edits write zero audit rows (diff bug fix)', function (): void {
    $dg       = makeUserWithRole('dg');
    $candidat = makeCandidat();

    $this->actingAs($dg)->putJson(
        "/api/admin/concours/candidats/{$candidat->id}",
        [
            'nom'            => 'DUPONT',           // unchanged
            'date_naissance' => '2000-01-15',       // unchanged (date cast trap)
            'deja_bac'       => true,               // unchanged (bool cast trap)
        ],
    )->assertOk()
        ->assertJson(['changed_fields' => []]);

    expect(CandidatModification::query()
        ->where('candidat_id', $candidat->id)
        ->where('channel', CandidatModification::CHANNEL_ADMIN)
        ->count())->toBe(0);
});

it('a candidat cannot edit any candidat via the admin endpoint', function (): void {
    $candidatRole = makeUserWithRole('candidat');
    $target       = makeCandidat();

    $this->actingAs($candidatRole)->putJson(
        "/api/admin/concours/candidats/{$target->id}",
        ['nom' => 'HACKED'],
    )->assertStatus(403);

    expect($target->fresh()->nom)->toBe('DUPONT');
});

it('chef-centre cannot edit a candidat outside their centre', function (): void {
    $cc       = makeUserWithRole('chef-centre');
    $candidat = makeCandidat();  // belongs to first centre; cc has no assignments

    $this->actingAs($cc)->putJson(
        "/api/admin/concours/candidats/{$candidat->id}",
        ['nom' => 'HACKED'],
    )->assertStatus(403);
});

it('strips centre_id from chef-centre payload even when they have edit:own_center', function (): void {
    // Assign the chef-centre to the candidat's centre so authorize passes,
    // but verify centre_id reassignment is still blocked.
    $cc       = makeUserWithRole('chef-centre');
    $candidat = makeCandidat();
    $otherCentre = Centre::query()->where('id', '!=', $candidat->centre_id)->first();

    \DB::table('chef_centre_assignments')->insert([
        'id'                  => \Ramsey\Uuid\Uuid::uuid4()->toString(),
        'user_id'             => $cc->id,
        'centre_id'           => $candidat->centre_id,
        'concours_session_id' => $candidat->concours_session_id,
        'created_at'          => now(),
        'updated_at'          => now(),
    ]);

    $this->actingAs($cc)->putJson(
        "/api/admin/concours/candidats/{$candidat->id}",
        [
            'nom'       => 'NEW-NOM',
            'centre_id' => $otherCentre->id,  // forbidden — should be silently stripped
        ],
    )->assertOk();

    $candidat->refresh();
    expect($candidat->nom)->toBe('NEW-NOM')
        ->and($candidat->centre_id)->not->toBe($otherCentre->id);
});

it('rejects duplicate email within the same session', function (): void {
    $dg = makeUserWithRole('dg');
    $a  = makeCandidat();
    $b  = Candidat::query()->create([
        ...$a->only([
            'concours_session_id', 'centre_id', 'date_naissance', 'lieu_naissance',
            'sexe', 'nationalite_id', 'deja_bac', 'annee_bac', 'serie_bac_id',
            'etablissement_frequente', 'section_premier_choix_id',
        ]),
        'nom' => 'AUTRE', 'prenom' => 'Personne',
        'email' => 'autre@example.test',
        'telephone' => '+241 06 99 88 77',
        'statut' => Candidat::STATUS_NON,
        'matricule_public' => 'CUK-EDITTEST0002',
    ]);

    $this->actingAs($dg)->putJson(
        "/api/admin/concours/candidats/{$b->id}",
        ['email' => $a->email],  // collision with A
    )->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

it('saving the same email back passes the unique rule', function (): void {
    $dg       = makeUserWithRole('dg');
    $candidat = makeCandidat();

    $this->actingAs($dg)->putJson(
        "/api/admin/concours/candidats/{$candidat->id}",
        ['email' => $candidat->email],
    )->assertOk()
        ->assertJson(['changed_fields' => []]);
});
