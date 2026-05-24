<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\AcademicStructure\Models\Section;
use Modules\Concours\Models\Candidat;
use Modules\Concours\Models\ChefCentreAssignment;
use Modules\Concours\Models\ConcoursSession;
use Modules\Referentiels\Models\Nationalite;
use Modules\Referentiels\Models\SerieBac;
use Modules\Reporting\Services\StatisticsService;
use Modules\UserManagement\Models\Role;
use Modules\UserManagement\Models\User;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(Modules\Referentiels\Database\Seeders\ReferentielsDatabaseSeeder::class);
    $this->seed(Modules\AcademicStructure\Database\Seeders\AcademicStructureDatabaseSeeder::class);
    $this->seed(Modules\UserManagement\Database\Seeders\UserManagementDatabaseSeeder::class);
    $this->seed(Modules\Concours\Database\Seeders\CentresSeeder::class);
    $this->seed(Modules\Concours\Database\Seeders\ConcoursSessionsSeeder::class);
});

function makeReportingCandidat(string $centreCode, string $status = 'valid', string $sexe = 'M'): Candidat
{
    $session = ConcoursSession::active();
    $centre  = Modules\Concours\Models\Centre::query()->where('code', $centreCode)->firstOrFail();
    $section = Section::query()->first();
    $bac     = SerieBac::query()->first();
    $nat     = Nationalite::query()->first();

    return Candidat::query()->create([
        'concours_session_id'      => $session->id,
        'centre_id'                => $centre->id,
        'nom' => 'TEST', 'prenom' => 'C',
        'date_naissance' => '2000-01-01', 'lieu_naissance' => 'X', 'sexe' => $sexe,
        'nationalite_id' => $nat->id,
        'email' => 'c-' . \Illuminate\Support\Str::random(6) . '@x.test',
        'telephone' => '06' . random_int(1000000, 9999999),
        'deja_bac' => true, 'annee_bac' => 2024,
        'serie_bac_id' => $bac->id, 'etablissement_frequente' => 'X',
        'section_premier_choix_id' => $section->id,
        'statut' => $status,
        'matricule_public' => 'CUK-' . strtoupper(\Illuminate\Support\Str::random(12)),
    ]);
}

function adminWithRole(string $role): User
{
    $u = User::factory()->create();
    $u->roles()->sync([Role::query()->where('code', $role)->value('id')]);
    return $u;
}

it('returns global summary counts per status', function (): void {
    makeReportingCandidat('CENTRE-LBV', 'non');
    makeReportingCandidat('CENTRE-LBV', 'valid');
    makeReportingCandidat('CENTRE-FCV', 'valid');
    makeReportingCandidat('CENTRE-FCV', 'rejete');

    $stats = app(StatisticsService::class)->summary(ConcoursSession::active(), adminWithRole('dg'));

    expect($stats['total'])->toBe(4)
        ->and($stats['pending'])->toBe(1)
        ->and($stats['paid'])->toBe(2)
        ->and($stats['rejected'])->toBe(1);
});

it('scopes counts to the chef-centre\'s centre via ScopedQuery', function (): void {
    $session = ConcoursSession::active();
    $lbv = Modules\Concours\Models\Centre::query()->where('code', 'CENTRE-LBV')->firstOrFail();

    makeReportingCandidat('CENTRE-LBV', 'valid');
    makeReportingCandidat('CENTRE-LBV', 'valid');
    makeReportingCandidat('CENTRE-FCV', 'valid'); // chef should NOT see this one

    $chef = adminWithRole('chef-centre');
    ChefCentreAssignment::query()->create([
        'concours_session_id' => $session->id,
        'centre_id'           => $lbv->id,
        'user_id'             => $chef->id,
        'est_principal'       => true,
        'assigned_at'         => now(),
    ]);

    $stats = app(StatisticsService::class)->summary($session, $chef);

    expect($stats['total'])->toBe(2);
});

it('aggregates by centre + by sexe correctly', function (): void {
    makeReportingCandidat('CENTRE-LBV', 'valid', 'M');
    makeReportingCandidat('CENTRE-LBV', 'valid', 'F');
    makeReportingCandidat('CENTRE-FCV', 'valid', 'F');

    $svc = app(StatisticsService::class);
    $admin = adminWithRole('dg');

    $byCentre = $svc->byCentre(ConcoursSession::active(), $admin);
    $bySex    = $svc->bySex(ConcoursSession::active(), $admin);

    expect(collect($byCentre)->firstWhere('label', 'Libreville')['value'])->toBe(2)
        ->and($bySex['male'])->toBe(1)
        ->and($bySex['female'])->toBe(2);
});

it('returns 403 for users without any reporting permission', function (): void {
    $candidat = adminWithRole('candidat');
    $this->actingAs($candidat)->get('/admin/reporting')->assertForbidden();
});

it('lets DG hit the dashboard and the chart endpoints', function (): void {
    $admin = adminWithRole('dg');
    $this->actingAs($admin)->get('/admin/reporting')->assertOk()->assertSee('Tableaux de bord');
    $this->actingAs($admin)->getJson('/api/admin/reporting/by-status')->assertOk()->assertJsonStructure([['label', 'value']]);
});
