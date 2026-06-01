<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\AcademicStructure\Models\Section;
use Modules\Concours\DTOs\ConfirmSelectionDto;
use Modules\Concours\DTOs\SaveNotesBatchDto;
use Modules\Concours\Exceptions\SelectionAlreadyPublishedException;
use Modules\Concours\Models\Candidat;
use Modules\Concours\Models\ConcoursSession;
use Modules\Concours\Models\Epreuve;
use Modules\Concours\Models\ResultPublication;
use Modules\Concours\Services\MoyenneCalculatorService;
use Modules\Concours\Services\NoteService;
use Modules\Concours\Services\SelectionService;
use Modules\Referentiels\Models\Nationalite;
use Modules\Referentiels\Models\SerieBac;
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
    $this->seed(Modules\Concours\Database\Seeders\EpreuvesSeeder::class);
    User::factory()->create(); // a user to stamp notes.entered_by_user_id
});

function makeValidCandidat(string $sectionCode, string $emailLocal): Candidat
{
    $session = ConcoursSession::active();
    $centre  = Modules\Concours\Models\Centre::query()->first();
    $section = Section::query()->where('code', $sectionCode)->firstOrFail();
    $bac     = SerieBac::query()->first();
    $nat     = Nationalite::query()->first();

    return Candidat::query()->create([
        'concours_session_id'      => $session->id,
        'centre_id'                => $centre->id,
        'nom' => 'TEST', 'prenom' => $emailLocal,
        'date_naissance' => '2000-01-01', 'lieu_naissance' => 'Libreville', 'sexe' => 'M',
        'nationalite_id' => $nat->id,
        'email' => "$emailLocal@test.example", 'telephone' => '06' . random_int(1000000, 9999999),
        'deja_bac' => true, 'annee_bac' => 2024,
        'serie_bac_id' => $bac->id, 'etablissement_frequente' => 'X',
        'section_premier_choix_id' => $section->id,
        'statut' => Candidat::STATUS_VALID,
        'matricule_public' => 'CUK-' . strtoupper(\Illuminate\Support\Str::random(12)),
    ]);
}

it('calculates moyenne pondérée and ranking per section', function (): void {
    $c1 = makeValidCandidat('IC', 'alpha'); // expected best
    $c2 = makeValidCandidat('IC', 'beta');
    $c3 = makeValidCandidat('IC', 'gamma');

    /** @var NoteService $notes */
    $notes = app(NoteService::class);
    $epreuves = Epreuve::query()->where('active', true)->get();

    foreach ([[$c1, [18, 17, 16]], [$c2, [12, 11, 14]], [$c3, [15, 14, 13]]] as [$candidat, $values]) {
        foreach ($epreuves as $idx => $epreuve) {
            $notes->saveBatch(new SaveNotesBatchDto(
                epreuveId: $epreuve->id,
                userId:    User::query()->first()->id,
                entries:   [[
                    'candidat_id' => $candidat->id,
                    'valeur'      => $values[$idx],
                    'absent'      => false,
                ]],
                lock: false,
            ));
        }
    }

    /** @var MoyenneCalculatorService $moy */
    $moy = app(MoyenneCalculatorService::class);
    $moy->recomputeForSession(ConcoursSession::active()->id);

    expect((float) $c1->refresh()->moyenne)->toBeGreaterThan((float) $c3->refresh()->moyenne)
        ->and((float) $c3->moyenne)->toBeGreaterThan((float) $c2->refresh()->moyenne)
        ->and($c1->rang)->toBe(1)
        ->and($c3->rang)->toBe(2)
        ->and($c2->rang)->toBe(3);
});

it('suggests admis sorted by moyenne capped by places_par_session', function (): void {
    Section::query()->where('code', 'IC')->update(['places_par_session' => 2]);

    foreach (['a', 'b', 'c'] as $i => $name) {
        $c = makeValidCandidat('IC', $name);
        $c->forceFill(['moyenne' => 18 - $i, 'rang' => $i + 1])->save();
    }

    /** @var SelectionService $sel */
    $sel = app(SelectionService::class);
    $proposal = $sel->suggest(ConcoursSession::active()->id);
    $section  = Section::query()->where('code', 'IC')->firstOrFail();

    expect($proposal->get($section->id)['candidats'])->toHaveCount(2);
});

it('confirms a selection, creates User accounts for admis and refuses double-publish', function (): void {
    $c1 = makeValidCandidat('IC', 'admis1');
    $c1->forceFill(['moyenne' => 16.5, 'rang' => 1])->save();
    $section = Section::query()->where('code', 'IC')->firstOrFail();

    /** @var SelectionService $sel */
    $sel = app(SelectionService::class);
    $admin = User::factory()->create();
    $admin->roles()->sync([Role::query()->where('code', 'super-admin')->value('id')]);

    $publication = $sel->confirm(new ConfirmSelectionDto(
        concoursSessionId: ConcoursSession::active()->id,
        publishedByUserId: $admin->id,
        admis: [['candidat_id' => $c1->id, 'orientation_section_id' => $section->id]],
    ));

    expect($publication)->toBeInstanceOf(ResultPublication::class)
        ->and($publication->total_admis)->toBe(1);

    $c1->refresh();
    expect($c1->statut)->toBe(Candidat::STATUS_ADMIS)
        ->and($c1->section_orientation_id)->toBe($section->id)
        // The candidat row is intentionally NOT mutated by promotion; the link
        // to the new étudiant account lives on users.promoted_from_candidat_id.
        ->and(User::query()->where('promoted_from_candidat_id', $c1->id)->exists())->toBeTrue();

    // second publish must fail
    $sel->confirm(new ConfirmSelectionDto(
        concoursSessionId: ConcoursSession::active()->id,
        publishedByUserId: $admin->id,
        admis: [['candidat_id' => $c1->id, 'orientation_section_id' => $section->id]],
    ));
})->throws(SelectionAlreadyPublishedException::class);

it('renders the public results page when a publication exists', function (): void {
    $c1 = makeValidCandidat('IC', 'pub1');
    $c1->forceFill(['moyenne' => 17.25, 'rang' => 1])->save();

    $section = Section::query()->where('code', 'IC')->firstOrFail();
    $admin = User::factory()->create();
    $admin->roles()->sync([Role::query()->where('code', 'super-admin')->value('id')]);

    app(SelectionService::class)->confirm(new ConfirmSelectionDto(
        concoursSessionId: ConcoursSession::active()->id,
        publishedByUserId: $admin->id,
        admis: [['candidat_id' => $c1->id, 'orientation_section_id' => $section->id]],
    ));

    $this->get('/resultats')->assertOk()->assertSee($c1->matricule_public);
});
