<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Concours\Models\Candidat;
use Modules\Concours\Models\CandidatDocument;
use Modules\Concours\Models\CandidatMotifRejet;
use Modules\Concours\Models\ConcoursSession;
use Modules\Concours\Models\Payment;
use Modules\Concours\Services\Legacy\LegacyImportOrchestrator;
use Modules\UserManagement\Services\LegacyDumpParser;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(Modules\Referentiels\Database\Seeders\ReferentielsDatabaseSeeder::class);
    $this->seed(Modules\AcademicStructure\Database\Seeders\AcademicStructureDatabaseSeeder::class);
    $this->seed(Modules\Concours\Database\Seeders\CentresSeeder::class);
    $this->seed(Modules\Concours\Database\Seeders\ConcoursSessionsSeeder::class);
});

function fixtureDump(): string
{
    return <<<'SQL'
        INSERT INTO `annees` (`idan`, `code`, `nom`) VALUES (6, '2025', NULL);

        INSERT INTO `concours` (`idconc`, `date_deb`, `date_fin`, `date_conc`, `idan`) VALUES
        (9, '2025-07-15', '2025-08-13', '2025-08-14', 6);

        INSERT INTO `centres` (`idcent`, `nom`, `code`, `idprov`, `idan`, `lieuconcour`) VALUES
        (174, 'Libreville', NULL, 1, 6, 'ENSET');

        INSERT INTO `sections` (`idsect`, `nom`, `code`, `idcyc`) VALUES
        (5, 'DUT en Informatique et Communication', 'IC', 1);

        INSERT INTO `series_bac` (`idbac`, `nom`, `code`) VALUES (2, 'Série D', 'D');

        INSERT INTO `documents` (`iddoc`, `nom`, `code`) VALUES
        (1, 'Copie légalisée de l''acte de naissance', 'acte');

        INSERT INTO `etudiants` (`idetu`, `nom`, `prenom`, `email`, `randomid`, `dejabac`, `annebac`, `dtnais`, `lieunais`, `nationalite`, `tel`, `sexe`, `valid`, `eta_fre`, `idan`, `idsect`, `idsect2`, `idcent`, `idbac`, `bac_name`) VALUES
        (42, 'NDONG', 'Alex', 'alex@old.test', NULL, 1, 2024, '2005-04-10', 'Libreville', 'Gabonais', '062345678', 'M', 'valid', 'Lycée Léon MBA', 6, 5, 0, 174, 2, NULL),
        (43, 'OBIANG', 'Marie', NULL, NULL, NULL, NULL, '2006-01-20', 'Oyem', 'Gabonais', '076543210', 'F', 'rejete', 'Lycée Henri Sylvoz', 6, 5, 0, 174, 2, NULL);

        INSERT INTO `documents_etudiants` (`iddoc`, `idetu`, `src`) VALUES
        (1, 42, '../documentcupk/2025user42acte.pdf');

        INSERT INTO `motifs` (`idetu`, `motif`) VALUES
        (43, 'Acte de naissance manquant');

        INSERT INTO `payments` (`id`, `id_etu`, `amount`, `ebilling_id`, `external_reference`, `status`, `payload`, `created_at`) VALUES
        (100, 42, 10300, 'BILL-X-42', 'CUK-42-001', 'PAID', NULL, '2025-08-01 10:00:00');
        SQL;
}

it('imports the canonical example end-to-end', function (): void {
    $parser = new LegacyDumpParser(fixtureDump());

    $report = app(LegacyImportOrchestrator::class)->run($parser, [], dryRun: false);

    expect($report->counts['concours_sessions']['imported'])->toBeGreaterThan(0)
        ->and($report->counts['candidats']['imported'])->toBe(2)
        ->and($report->counts['candidat_documents']['imported'])->toBe(1)
        ->and($report->counts['candidat_motifs_rejet']['imported'])->toBe(1)
        ->and($report->counts['payments']['imported'])->toBe(1);

    $alex = Candidat::query()->where('legacy_id', 42)->first();
    expect($alex)->not->toBeNull()
        ->and($alex->nom)->toBe('NDONG')
        ->and($alex->prenom)->toBe('Alex')
        ->and($alex->statut)->toBe(Candidat::STATUS_VALID)
        ->and($alex->matricule_public)->toStartWith('CUK-')
        ->and($alex->documents()->count())->toBe(1);

    $marie = Candidat::query()->where('legacy_id', 43)->first();
    expect($marie->email)->toBeNull()                 // null email preserved
        ->and($marie->statut)->toBe(Candidat::STATUS_REJETE)
        ->and($marie->motifsRejet()->count())->toBe(1);

    $payment = Payment::query()->where('legacy_id', 100)->first();
    expect($payment->external_reference)->toBe('CUK-42-001')
        ->and($payment->status)->toBe(Payment::STATUS_PAID)
        ->and($payment->signature_verified)->toBeFalse();
});

it('is idempotent — a second run skips everything', function (): void {
    $parser = new LegacyDumpParser(fixtureDump());
    $svc = app(LegacyImportOrchestrator::class);

    $first  = $svc->run($parser, [], false);
    $second = $svc->run($parser, [], false);

    expect($first->counts['candidats']['imported'])->toBe(2)
        ->and($second->counts['candidats']['imported'])->toBe(0)
        ->and($second->counts['candidats']['skipped'])->toBe(2);

    expect(Candidat::query()->count())->toBe(2); // no duplicates
});

it('--dry-run reports counts without writing any row', function (): void {
    $parser = new LegacyDumpParser(fixtureDump());
    $report = app(LegacyImportOrchestrator::class)->run($parser, [], dryRun: true);

    expect($report->counts['candidats']['imported'])->toBe(2)
        ->and(Candidat::query()->count())->toBe(0)
        ->and(CandidatDocument::query()->count())->toBe(0)
        ->and(CandidatMotifRejet::query()->count())->toBe(0)
        ->and(Payment::query()->count())->toBe(0);
});

it('imports only the subset of tables requested', function (): void {
    $parser = new LegacyDumpParser(fixtureDump());

    $report = app(LegacyImportOrchestrator::class)->run(
        $parser,
        ['concours_sessions', 'candidats'],
        dryRun: false,
    );

    expect(Candidat::query()->count())->toBe(2)
        ->and(CandidatDocument::query()->count())->toBe(0)
        ->and(Payment::query()->count())->toBe(0);
});

// --- Dedupe regression: contact + (name OR DOB) ----------------------------

function sharedContactDump(): string
{
    // Reference tables (minimal) + four etudiants sharing contacts:
    //   A1/A2 — two DIFFERENT people sharing ONE phone (parent's number).
    //           Different names AND different DOB → must stay SEPARATE.
    //   B1/B2 — the SAME person who registered twice with a DOB typo.
    //           Same name, same phone → must MERGE into one.
    return <<<'SQL'
        INSERT INTO `annees` (`idan`, `code`, `nom`) VALUES (6, '2025', NULL);
        INSERT INTO `concours` (`idconc`, `date_deb`, `date_fin`, `date_conc`, `idan`) VALUES
        (9, '2025-07-15', '2025-08-13', '2025-08-14', 6);
        INSERT INTO `centres` (`idcent`, `nom`, `code`, `idprov`, `idan`, `lieuconcour`) VALUES
        (174, 'Libreville', NULL, 1, 6, 'ENSET');
        INSERT INTO `sections` (`idsect`, `nom`, `code`, `idcyc`) VALUES
        (5, 'DUT en Informatique et Communication', 'IC', 1);
        INSERT INTO `series_bac` (`idbac`, `nom`, `code`) VALUES (2, 'Série D', 'D');

        INSERT INTO `etudiants` (`idetu`, `nom`, `prenom`, `email`, `randomid`, `dejabac`, `annebac`, `dtnais`, `lieunais`, `nationalite`, `tel`, `sexe`, `valid`, `eta_fre`, `idan`, `idsect`, `idsect2`, `idcent`, `idbac`, `bac_name`) VALUES
        (201, 'KHAKI MAPANGOU', 'Kriss',  NULL, NULL, NULL, NULL, '2002-09-15', 'Mouila', 'Gabonais', '062331994', 'M', 'valid', 'Lyc', 6, 5, 0, 174, 2, NULL),
        (202, 'IGNEMBI MOADHYS', 'Alicia', NULL, NULL, NULL, NULL, '2006-06-06', 'Mouila', 'Gabonais', '062331994', 'F', 'valid', 'Lyc', 6, 5, 0, 174, 2, NULL),
        (203, 'NDOUMOU', 'Paul', NULL, NULL, NULL, NULL, '2004-03-02', 'Libreville', 'Gabonais', '077000111', 'M', 'oui',   'Lyc', 6, 5, 0, 174, 2, NULL),
        (204, 'NDOUMOU', 'Paul', NULL, NULL, NULL, NULL, '2004-03-20', 'Libreville', 'Gabonais', '077000111', 'M', 'valid', 'Lyc', 6, 5, 0, 174, 2, NULL),
        (205, 'MBEGHA', 'Mouhamed', 'shared@gmail.com', NULL, NULL, NULL, '2005-08-27', 'Oyem', 'Gabonais', '066111000', 'M', 'valid', 'Lyc', 6, 5, 0, 174, 2, NULL),
        (206, 'MBA MBA', 'Pierre',   'shared@gmail.com', NULL, NULL, NULL, '2000-07-10', 'Oyem', 'Gabonais', '066222000', 'M', 'valid', 'Lyc', 6, 5, 0, 174, 2, NULL);
        SQL;
}

it('keeps two different people who share a phone (no false merge)', function (): void {
    $parser = new LegacyDumpParser(sharedContactDump());
    $report = app(LegacyImportOrchestrator::class)->run($parser, [], dryRun: false);

    // 201 + 202 are distinct humans (different name AND DOB) sharing 062331994
    // → both imported. 203 + 204 are the same person (same name, DOB typo)
    // → merged. Net: 3 candidats.
    $kriss = Candidat::query()->where('legacy_id', 201)->first();
    $alicia = Candidat::query()->where('legacy_id', 202)->first();

    expect($kriss)->not->toBeNull()
        ->and($alicia)->not->toBeNull()
        ->and($kriss->prenom)->toBe('Kriss')
        ->and($alicia->prenom)->toBe('Alicia')
        ->and($kriss->statut)->toBe(Candidat::STATUS_VALID)
        ->and($alicia->statut)->toBe(Candidat::STATUS_VALID);
});

it('still merges the same person who registered twice with a DOB typo', function (): void {
    $parser = new LegacyDumpParser(sharedContactDump());
    app(LegacyImportOrchestrator::class)->run($parser, [], dryRun: false);

    // 203 (oui) + 204 (valid) — same name + phone, 18-day DOB typo → one row,
    // the higher-status (valid) winner kept.
    $ndoumou = Candidat::query()->where('nom', 'NDOUMOU')->get();
    expect($ndoumou)->toHaveCount(1)
        ->and($ndoumou->first()->statut)->toBe(Candidat::STATUS_VALID);

    // Grand total: Kriss + Alicia + one NDOUMOU + Mouhamed + Pierre = 5.
    expect(Candidat::query()->count())->toBe(5);
});

it('plus-tags a shared email so both distinct people import + stay unique', function (): void {
    $parser = new LegacyDumpParser(sharedContactDump());
    app(LegacyImportOrchestrator::class)->run($parser, [], dryRun: false);

    // 205 (MBEGHA Mouhamed) + 206 (MBA MBA Pierre) — different name AND DOB,
    // same email shared@gmail.com, different phones → BOTH imported. The first
    // keeps the clean address; the second is plus-tagged with its legacy id so
    // the (session, email) unique index holds.
    $mouhamed = Candidat::query()->where('legacy_id', 205)->first();
    $pierre   = Candidat::query()->where('legacy_id', 206)->first();

    expect($mouhamed)->not->toBeNull()
        ->and($pierre)->not->toBeNull()
        ->and($mouhamed->email)->toBe('shared@gmail.com')
        ->and($pierre->email)->toBe('shared+206@gmail.com');

    // No two candidats in the session share a normalized email.
    $distinct = Candidat::query()->get()
        ->groupBy(fn ($c) => $c->concours_session_id . '|' . mb_strtolower((string) $c->email))
        ->every(fn ($g) => $g->count() === 1);
    expect($distinct)->toBeTrue();
});
