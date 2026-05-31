<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Concours\Models\Centre;
use Modules\Concours\Models\ChefCentreAssignment;
use Modules\Concours\Models\ConcoursSession;
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

function makeUserWithRoleCC(string $code): User
{
    $user = User::query()->create([
        'matricule'  => str_pad((string) random_int(1, 99999), 5, '0', STR_PAD_LEFT),
        'nom'        => 'Test',
        'prenom'     => ucfirst($code),
        'email'      => $code . '-' . random_int(1, 99999) . '@example.test',
        'password'   => bcrypt('test1234'),
    ]);
    $user->roles()->attach(Role::query()->where('code', $code)->first());
    return $user->fresh();
}

// ----------------------------------------------- centres CRUD

it('DG can list, create, update, and toggle a centre', function (): void {
    $dg = makeUserWithRoleCC('dg');

    // GET index
    $this->actingAs($dg)->get('/admin/concours/centres')
        ->assertOk()
        ->assertSee('Nouveau centre');

    // POST create
    $this->actingAs($dg)->post('/admin/concours/centres', [
        'code' => 'TEST-CC-01', 'nom' => 'Test Centre',
        'ville' => 'Libreville', 'capacite_par_defaut' => 250, 'active' => 1,
    ])->assertRedirect();

    $created = Centre::query()->where('code', 'TEST-CC-01')->first();
    expect($created)->not->toBeNull()
        ->and($created->nom)->toBe('Test Centre')
        ->and($created->capacite_par_defaut)->toBe(250);

    // PATCH update
    $this->actingAs($dg)->patch('/admin/concours/centres/' . $created->id, [
        'code' => 'TEST-CC-01', 'nom' => 'Test Centre Renommé',
        'capacite_par_defaut' => 400,
    ])->assertRedirect();
    expect($created->refresh()->nom)->toBe('Test Centre Renommé')
        ->and($created->capacite_par_defaut)->toBe(400);

    // POST toggle
    $this->actingAs($dg)->post('/admin/concours/centres/' . $created->id . '/toggle')
        ->assertRedirect();
    expect($created->refresh()->active)->toBeFalse();
});

it('chef-centre cannot reach the centres CRUD page', function (): void {
    $cc = makeUserWithRoleCC('chef-centre');

    $this->actingAs($cc)->get('/admin/concours/centres')->assertStatus(403);
    $this->actingAs($cc)->post('/admin/concours/centres', [
        'code' => 'X', 'nom' => 'Y',
    ])->assertStatus(403);
});

it('rejects duplicate centre codes', function (): void {
    $dg = makeUserWithRoleCC('dg');

    $this->actingAs($dg)->post('/admin/concours/centres', [
        'code' => 'DUP-01', 'nom' => 'A',
    ])->assertRedirect();

    $this->actingAs($dg)->post('/admin/concours/centres', [
        'code' => 'DUP-01', 'nom' => 'B',
    ])->assertSessionHasErrors(['code']);
});

// ----------------------------------------------- chef-centre matrix

it('DG can view, assign, toggle and revoke chef-centre assignments', function (): void {
    $dg      = makeUserWithRoleCC('dg');
    $ccUser  = makeUserWithRoleCC('chef-centre');
    $centre  = Centre::query()->where('active', true)->first();
    $session = ConcoursSession::active();

    // GET matrix
    $this->actingAs($dg)->get('/admin/concours/chef-centres')
        ->assertOk()
        ->assertSee('Ajouter un chef');

    // POST assign
    $this->actingAs($dg)->post('/admin/concours/chef-centres/assign', [
        'concours_session_id' => $session->id,
        'centre_id'           => $centre->id,
        'user_id'             => $ccUser->id,
        'est_principal'       => 1,
    ])->assertRedirect();

    $assignment = ChefCentreAssignment::query()
        ->where('user_id', $ccUser->id)
        ->where('centre_id', $centre->id)
        ->where('concours_session_id', $session->id)
        ->first();
    expect($assignment)->not->toBeNull()
        ->and($assignment->est_principal)->toBeTrue();

    // POST toggle principal
    $this->actingAs($dg)->post('/admin/concours/chef-centres/' . $assignment->id . '/principal')
        ->assertRedirect();
    expect($assignment->refresh()->est_principal)->toBeFalse();

    // DELETE revoke
    $this->actingAs($dg)->delete('/admin/concours/chef-centres/' . $assignment->id)
        ->assertRedirect();
    expect(ChefCentreAssignment::query()->find($assignment->id))->toBeNull()
        ->and(ChefCentreAssignment::withTrashed()->find($assignment->id))->not->toBeNull();
});

it('re-assigning the same (session, centre, user) tuple restores the soft-deleted row', function (): void {
    $dg      = makeUserWithRoleCC('dg');
    $ccUser  = makeUserWithRoleCC('chef-centre');
    $centre  = Centre::query()->where('active', true)->first();
    $session = ConcoursSession::active();

    // Assign, then delete, then re-assign — should restore not duplicate.
    $this->actingAs($dg)->post('/admin/concours/chef-centres/assign', [
        'concours_session_id' => $session->id, 'centre_id' => $centre->id,
        'user_id' => $ccUser->id, 'est_principal' => 1,
    ]);
    $a = ChefCentreAssignment::query()->where('user_id', $ccUser->id)->first();
    $this->actingAs($dg)->delete('/admin/concours/chef-centres/' . $a->id);
    $this->actingAs($dg)->post('/admin/concours/chef-centres/assign', [
        'concours_session_id' => $session->id, 'centre_id' => $centre->id,
        'user_id' => $ccUser->id, 'est_principal' => 0,
    ]);

    expect(ChefCentreAssignment::query()->where('user_id', $ccUser->id)->count())->toBe(1)
        ->and(ChefCentreAssignment::query()->where('user_id', $ccUser->id)->first()->est_principal)->toBeFalse();
});

it('chef-centre cannot reach the assignment matrix', function (): void {
    $cc = makeUserWithRoleCC('chef-centre');
    $this->actingAs($cc)->get('/admin/concours/chef-centres')->assertStatus(403);
});

it('assignment busts the resolver cache so the chef sees their centre immediately', function (): void {
    $dg      = makeUserWithRoleCC('dg');
    $ccUser  = makeUserWithRoleCC('chef-centre');
    $centre  = Centre::query()->where('active', true)->first();
    $session = ConcoursSession::active();

    $resolver = app(\App\Foundation\Identity\Contracts\UserScopeResolver::class);
    // Prime the cache with the empty result.
    expect($resolver->accessibleCentreIds($ccUser))->toBe([]);

    // Assign. The controller must forget the cache key.
    $this->actingAs($dg)->post('/admin/concours/chef-centres/assign', [
        'concours_session_id' => $session->id, 'centre_id' => $centre->id,
        'user_id' => $ccUser->id, 'est_principal' => 1,
    ]);

    // Resolver picks up the new centre on the very next call (no 60s wait).
    expect($resolver->accessibleCentreIds($ccUser))->toContain($centre->id);
});
