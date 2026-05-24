<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\AcademicStructure\Models\AnneeAcademique;
use Modules\AcademicStructure\Models\Cycle;
use Modules\AcademicStructure\Models\Section;
use Modules\UserManagement\Models\Role;
use Modules\UserManagement\Models\User;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(Modules\UserManagement\Database\Seeders\RoleSeeder::class);
    $this->seed(Modules\AcademicStructure\Database\Seeders\AcademicStructureDatabaseSeeder::class);
});

function asAcademicAdmin(): User
{
    $user = User::factory()->create();
    $user->roles()->sync([Role::query()->where('code', 'super-admin')->value('id')]);
    return $user;
}

it('seeds CUK + 7 départements + 7 sections + 3 années', function (): void {
    expect(\Modules\AcademicStructure\Models\Faculte::query()->count())->toBe(1)
        ->and(\Modules\AcademicStructure\Models\Departement::query()->count())->toBe(7)
        ->and(Section::query()->count())->toBe(7)
        ->and(AnneeAcademique::query()->count())->toBe(3);
});

it('exposes a public listing of sections without auth', function (): void {
    $this->getJson('/api/academic/sections/public')
        ->assertOk()
        ->assertJsonCount(7, 'data');
});

it('rejects mutations from unauthenticated users', function (): void {
    $this->postJson('/api/academic/cycles', ['code' => 'X', 'nom' => 'Y', 'duree_annees' => 2])
        ->assertRedirect('/login');
});

it('lets an authenticated super-admin create a niveau', function (): void {
    $dut = Cycle::query()->where('code', 'DUT')->firstOrFail();

    $this->actingAs(asAcademicAdmin())
        ->postJson('/api/academic/niveaux', [
            'cycle_id' => $dut->id,
            'code'     => 'DUT-EXTRA',
            'libelle'  => 'Niveau de test',
            'ordre'    => 3,
        ])->assertCreated();
});

it('rejects a section referencing an unknown cycle (FK validation)', function (): void {
    $this->actingAs(asAcademicAdmin())
        ->postJson('/api/academic/sections', [
            'cycle_id' => '00000000-0000-0000-0000-000000000000',
            'code'     => 'BAD',
            'nom'      => 'Bad section',
        ])->assertStatus(422)
          ->assertJsonValidationErrors('cycle_id');
});

it('enforces only one année académique courante via the partial unique index', function (): void {
    $a2025 = AnneeAcademique::query()->where('code', '2025-2026')->firstOrFail();
    $a2026 = AnneeAcademique::query()->where('code', '2026-2027')->firstOrFail();

    expect($a2025->est_courante)->toBeTrue()
        ->and($a2026->est_courante)->toBeFalse();

    $a2026->markAsCurrent();

    expect($a2026->refresh()->est_courante)->toBeTrue()
        ->and($a2025->refresh()->est_courante)->toBeFalse()
        ->and(AnneeAcademique::current()->code)->toBe('2026-2027');
});

it('forbids a delete that would orphan downstream rows (cycle has sections → restrict)', function (): void {
    $dut = Cycle::query()->where('code', 'DUT')->firstOrFail();

    // Force-delete bypasses soft-delete and triggers the FK; deleted_at-style
    // soft delete is safe because rows remain visible to FK.
    expect(fn () => $dut->forceDelete())->toThrow(\Illuminate\Database\QueryException::class);
});

it('exposes the active annee via the model helper', function (): void {
    expect(AnneeAcademique::current()?->code)->toBe('2025-2026');
});
