<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Referentiels\Models\Nationalite;
use Modules\Referentiels\Models\Province;
use Modules\UserManagement\Models\Role;
use Modules\UserManagement\Models\User;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(Modules\UserManagement\Database\Seeders\RoleSeeder::class);
    $this->seed(Modules\Referentiels\Database\Seeders\ReferentielsDatabaseSeeder::class);
});

function asSuperAdmin(): User
{
    $user = User::factory()->create();
    $user->roles()->sync([Role::query()->where('code', 'super-admin')->value('id')]);
    return $user;
}

it('seeds the 9 Gabonese provinces', function (): void {
    expect(Province::query()->count())->toBe(9);
});

it('exposes a public read endpoint without auth', function (): void {
    $this->getJson('/api/referentiels/provinces/public')
        ->assertOk()
        ->assertJsonStructure(['data' => [['id', 'code', 'nom']]])
        ->assertJsonCount(9, 'data');
});

it('rejects unauthenticated admin reads', function (): void {
    $this->getJson('/api/referentiels/provinces')->assertRedirect('/login');
});

it('lists rows for an authenticated super-admin', function (): void {
    $this->actingAs(asSuperAdmin())
        ->getJson('/api/referentiels/nationalites?per_page=10')
        ->assertOk()
        ->assertJsonStructure(['data', 'links', 'meta']);
});

it('creates a row', function (): void {
    $this->actingAs(asSuperAdmin())
        ->postJson('/api/referentiels/nationalites', [
            'code_iso' => 'SI',
            'nom'      => 'Slovène (test)',
        ])->assertCreated();
});

it('rejects an unknown slug', function (): void {
    $this->actingAs(asSuperAdmin())
        ->getJson('/api/referentiels/nope/public')
        ->assertStatus(500); // InvalidArgumentException → 500 in default handler
});

it('soft-deletes then restores a row', function (): void {
    $user = asSuperAdmin();
    $nat = Nationalite::query()->first();

    $this->actingAs($user)->deleteJson("/api/referentiels/nationalites/{$nat->id}")
        ->assertNoContent();

    expect(Nationalite::query()->find($nat->id))->toBeNull()
        ->and(Nationalite::query()->withTrashed()->find($nat->id))->not->toBeNull();

    $this->actingAs($user)->postJson("/api/referentiels/nationalites/{$nat->id}/restore")
        ->assertOk();

    expect(Nationalite::query()->find($nat->id))->not->toBeNull();
});

it('denies a candidat-role user from editing referentials', function (): void {
    $user = User::factory()->create();
    $user->roles()->sync([Role::query()->where('code', 'candidat')->value('id')]);

    $this->actingAs($user)
        ->postJson('/api/referentiels/series-bac', ['code' => 'TEST', 'nom' => 'Test'])
        ->assertForbidden();
});
