<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\AcademicStructure\Models\Section;
use Modules\Concours\Models\Candidat;
use Modules\Concours\Models\Centre;
use Modules\Concours\Models\ConcoursSession;
use Modules\Concours\Models\ConcoursSessionCentre;
use Modules\Concours\Models\Epreuve;
use Modules\Concours\Models\EpreuvePlanning;
use Modules\Concours\Models\ResultPublication;
use Modules\Concours\Services\PlanningService;
use Modules\Referentiels\Models\Nationalite;
use Modules\Referentiels\Models\SerieBac;
use Modules\Referentiels\Models\TypeEpreuve;
use Modules\UserManagement\Models\Role;
use Modules\UserManagement\Models\User;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(Modules\Referentiels\Database\Seeders\ReferentielsDatabaseSeeder::class);
    $this->seed(Modules\AcademicStructure\Database\Seeders\AcademicStructureDatabaseSeeder::class);
    $this->seed(Modules\UserManagement\Database\Seeders\UserManagementDatabaseSeeder::class);
    $this->seed(Modules\Parametrage\Database\Seeders\SettingsSeeder::class);
    $this->seed(Modules\Concours\Database\Seeders\CentresSeeder::class);
    $this->seed(Modules\Concours\Database\Seeders\ConcoursSessionsSeeder::class);
});

function edtMakeCandidat(string $sectionCode, string $centreId): Candidat
{
    $section = Section::query()->where('code', $sectionCode)->firstOrFail();

    return Candidat::query()->create([
        'concours_session_id'      => ConcoursSession::active()->id,
        'centre_id'                => $centreId,
        'nom' => 'EDT', 'prenom' => Str::random(5),
        'date_naissance' => '2000-01-01', 'lieu_naissance' => 'X', 'sexe' => 'M',
        'nationalite_id' => Nationalite::query()->value('id'),
        'email' => Str::random(8) . '@edt.test', 'telephone' => '06' . random_int(1000000, 9999999),
        'deja_bac' => true, 'annee_bac' => 2024,
        'serie_bac_id' => SerieBac::query()->value('id'), 'etablissement_frequente' => 'X',
        'section_premier_choix_id' => $section->id,
        'statut' => Candidat::STATUS_VALID,
        'matricule_public' => 'CUK-' . strtoupper(Str::random(12)),
    ]);
}

function edtMakeEpreuve(string $code, array $sectionIds): Epreuve
{
    $epreuve = Epreuve::query()->create([
        'concours_session_id' => ConcoursSession::active()->id,
        'type_epreuve_id'     => TypeEpreuve::query()->where('code', 'ecrit')->value('id'),
        'code' => $code, 'libelle' => $code,
        'coefficient' => 2, 'duree_minutes' => 120, 'note_max' => 20, 'ordre' => 1, 'active' => true,
    ]);
    $epreuve->sections()->sync($sectionIds);

    return $epreuve;
}

it('drives épreuve eligibility from the multi-section pivot', function (): void {
    $ic  = Section::query()->where('code', 'IC')->firstOrFail();
    $aec = Section::query()->where('code', 'AEC')->firstOrFail();
    $centreId = (string) Centre::query()->value('id');

    $epreuve = edtMakeEpreuve('MULTI', [$ic->id, $aec->id]);

    $cIc  = edtMakeCandidat('IC', $centreId);
    $cAec = edtMakeCandidat('AEC', $centreId);
    $cGte = edtMakeCandidat('GTE', $centreId);

    $eligible = $epreuve->eligibleCandidatsQuery()->pluck('id');

    expect($eligible)->toContain($cIc->id)
        ->and($eligible)->toContain($cAec->id)
        ->and($eligible)->not->toContain($cGte->id);
});

it('builds the candidate emploi du temps scoped to centre + section, including break lines', function (): void {
    $session = ConcoursSession::active();
    $centre  = Centre::query()->first();
    $ic      = Section::query()->where('code', 'IC')->firstOrFail();
    $aec     = Section::query()->where('code', 'AEC')->firstOrFail();

    // Attach the centre to the session and grab the pivot id.
    $sc = ConcoursSessionCentre::query()->firstOrCreate(
        ['concours_session_id' => $session->id, 'centre_id' => $centre->id],
        ['active' => true],
    );

    $epIc  = edtMakeEpreuve('EP-IC',  [$ic->id]);
    $epAec = edtMakeEpreuve('EP-AEC', [$aec->id]);

    // IC épreuve slot
    EpreuvePlanning::query()->create([
        'epreuve_id' => $epIc->id, 'kind' => 'epreuve', 'concours_session_centre_id' => $sc->id,
        'date_epreuve' => '2026-08-14', 'heure_debut' => '08:00:00', 'heure_fin' => '10:00:00', 'ordre' => 1,
    ]);
    // AEC épreuve slot — an IC candidat must NOT see this one
    EpreuvePlanning::query()->create([
        'epreuve_id' => $epAec->id, 'kind' => 'epreuve', 'concours_session_centre_id' => $sc->id,
        'date_epreuve' => '2026-08-14', 'heure_debut' => '10:00:00', 'heure_fin' => '12:00:00', 'ordre' => 2,
    ]);
    // Free line (pause) — applies to everyone at the centre
    EpreuvePlanning::query()->create([
        'epreuve_id' => null, 'kind' => 'pause', 'libelle_libre' => 'Pause déjeuner',
        'concours_session_centre_id' => $sc->id,
        'date_epreuve' => '2026-08-14', 'heure_debut' => '12:00:00', 'heure_fin' => '13:00:00', 'ordre' => 3,
    ]);

    $candidat = edtMakeCandidat('IC', (string) $centre->id);
    $planning = app(PlanningService::class)->planningForCandidat($candidat);

    expect($planning)->toHaveCount(2)                                  // IC épreuve + the break, NOT AEC
        ->and($planning->firstWhere('epreuve_id', $epIc->id))->not->toBeNull()
        ->and($planning->firstWhere('epreuve_id', $epAec->id))->toBeNull()
        ->and($planning->first(fn (EpreuvePlanning $p) => $p->isBreak()))->not->toBeNull();
});

it('attaches a PV pdf to the publication when results are published', function (): void {
    Storage::fake('public');

    $section = Section::query()->where('code', 'IC')->firstOrFail();
    $candidat = edtMakeCandidat('IC', (string) Centre::query()->value('id'));
    $candidat->forceFill(['moyenne' => 15.5, 'rang' => 1])->save();

    $admin = User::factory()->create();
    $admin->roles()->sync([Role::query()->where('code', 'super-admin')->value('id')]);

    $pv = UploadedFile::fake()->create('proces-verbal.pdf', 80, 'application/pdf');

    // Bypass the back-office route middleware (active-role / 2FA gates that
    // redirect 302); the ConfirmSelectionRequest still authorizes via can().
    $this->actingAs($admin)
        ->withoutMiddleware()
        ->post('/api/admin/concours/selection/confirm', [
            'concours_session_id' => ConcoursSession::active()->id,
            'admis'               => [['candidat_id' => $candidat->id, 'orientation_section_id' => $section->id]],
            'pv'                  => $pv,
        ])
        ->assertCreated();

    $pub = ResultPublication::latestActiveFor(ConcoursSession::active()->id);

    expect($pub)->not->toBeNull()
        ->and($pub->fichier_disk)->toBe('public')
        ->and($pub->fichier_path)->not->toBeNull();

    Storage::disk('public')->assertExists($pub->fichier_path);
});
