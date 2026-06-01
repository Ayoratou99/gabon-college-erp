<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\AcademicStructure\Models\Section;
use Modules\Concours\DTOs\SaveNotesBatchDto;
use Modules\Concours\Exceptions\EpreuveNotApplicableException;
use Modules\Concours\Models\Candidat;
use Modules\Concours\Models\ConcoursSession;
use Modules\Concours\Models\Epreuve;
use Modules\Concours\Services\NoteService;
use Modules\Referentiels\Models\Nationalite;
use Modules\Referentiels\Models\SerieBac;
use Modules\UserManagement\Models\User;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(Modules\Referentiels\Database\Seeders\ReferentielsDatabaseSeeder::class);
    $this->seed(Modules\AcademicStructure\Database\Seeders\AcademicStructureDatabaseSeeder::class);
    $this->seed(Modules\UserManagement\Database\Seeders\UserManagementDatabaseSeeder::class);
    $this->seed(Modules\Concours\Database\Seeders\CentresSeeder::class);
    $this->seed(Modules\Concours\Database\Seeders\ConcoursSessionsSeeder::class);
    $this->seed(Modules\Concours\Database\Seeders\EpreuvesSeeder::class);
    User::factory()->create(); // a user to stamp notes.entered_by_user_id
});

function makeCandidatFor(string $sectionCode): Candidat
{
    $session = ConcoursSession::active();
    $centre  = Modules\Concours\Models\Centre::query()->first();
    $section = Section::query()->where('code', $sectionCode)->firstOrFail();
    $bac     = SerieBac::query()->first();
    $nat     = Nationalite::query()->first();

    return Candidat::query()->create([
        'concours_session_id'      => $session->id,
        'centre_id'                => $centre->id,
        'nom' => 'TEST', 'prenom' => 'C-' . \Illuminate\Support\Str::random(4),
        'date_naissance' => '2000-01-01', 'lieu_naissance' => 'X', 'sexe' => 'M',
        'nationalite_id' => $nat->id,
        'email' => \Illuminate\Support\Str::random(8) . '@x.test', 'telephone' => '06' . random_int(1000000, 9999999),
        'deja_bac' => true, 'annee_bac' => 2024,
        'serie_bac_id' => $bac->id, 'etablissement_frequente' => 'X',
        'section_premier_choix_id' => $section->id,
        'statut' => Candidat::STATUS_VALID,
        'matricule_public' => 'CUK-' . strtoupper(\Illuminate\Support\Str::random(12)),
    ]);
}

it('saves a batch of notes for eligible candidats', function (): void {
    $c1 = makeCandidatFor('IC');
    $c2 = makeCandidatFor('IC');
    $epreuve = Epreuve::query()->where('code', 'MATH-DUT')->firstOrFail();

    $saved = app(NoteService::class)->saveBatch(new SaveNotesBatchDto(
        epreuveId: $epreuve->id,
        userId:    User::query()->first()->id,
        entries:   [
            ['candidat_id' => $c1->id, 'valeur' => 15.5, 'absent' => false],
            ['candidat_id' => $c2->id, 'valeur' => null,  'absent' => true],
        ],
    ));

    expect($saved)->toBe(2)
        ->and(\Modules\Concours\Models\Note::query()->where('candidat_id', $c1->id)->value('valeur'))->toEqual(15.5)
        ->and(\Modules\Concours\Models\Note::query()->where('candidat_id', $c2->id)->value('absent'))->toBeTrue();
});

it('rejects an out-of-range note', function (): void {
    $c = makeCandidatFor('IC');
    $epreuve = Epreuve::query()->where('code', 'MATH-DUT')->firstOrFail();

    app(NoteService::class)->saveBatch(new SaveNotesBatchDto(
        epreuveId: $epreuve->id,
        userId:    User::query()->first()->id,
        entries:   [['candidat_id' => $c->id, 'valeur' => 999, 'absent' => false]],
    ));
})->throws(InvalidArgumentException::class, 'hors plage');

it('rejects a note for a candidat not eligible for the epreuve', function (): void {
    // Build an epreuve scoped to a different section than the candidat's choice
    $session = ConcoursSession::active();
    $otherSection = Section::query()->where('code', 'AEC')->firstOrFail();
    $typeEcrit = Modules\Referentiels\Models\TypeEpreuve::query()->where('code', 'ecrit')->firstOrFail();

    $aecOnly = Epreuve::query()->create([
        'concours_session_id' => $session->id,
        'type_epreuve_id'     => $typeEcrit->id,
        'code' => 'AEC-ONLY', 'libelle' => 'Dessin technique',
        'scope_type' => Epreuve::SCOPE_SECTION,
        'scope_id'   => $otherSection->id,
        'coefficient' => 2, 'duree_minutes' => 120, 'note_max' => 20, 'ordre' => 99,
        'active' => true,
    ]);
    // Eligibility is driven by the epreuve_sections pivot now.
    $aecOnly->sections()->sync([$otherSection->id]);

    $ic = makeCandidatFor('IC');

    app(NoteService::class)->saveBatch(new SaveNotesBatchDto(
        epreuveId: $aecOnly->id,
        userId:    User::query()->first()->id,
        entries:   [['candidat_id' => $ic->id, 'valeur' => 12, 'absent' => false]],
    ));
})->throws(EpreuveNotApplicableException::class);
