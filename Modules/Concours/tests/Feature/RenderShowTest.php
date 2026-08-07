<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\AcademicStructure\Models\Section;
use Modules\Concours\Models\Candidat;
use Modules\Concours\Models\ConcoursSession;
use Modules\Referentiels\Models\Nationalite;
use Modules\Referentiels\Models\SerieBac;
use Modules\UserManagement\Http\Middleware\EnsureActiveRole;
use Modules\UserManagement\Http\Middleware\EnsureTwoFactorVerified;
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

function makeShowCandidat(string $statut, ?Modules\Concours\Models\Centre $centre = null): Candidat
{
    return Candidat::query()->create([
        'concours_session_id'      => ConcoursSession::active()->id,
        'centre_id'                => ($centre ?? Modules\Concours\Models\Centre::query()->first())->id,
        'nom' => 'RENDER', 'prenom' => 'Test',
        'date_naissance' => '2000-01-01', 'lieu_naissance' => 'X', 'sexe' => 'M',
        'nationalite_id' => Nationalite::query()->first()->id,
        'email' => 'render' . \Illuminate\Support\Str::random(6) . '@x.test',
        'telephone' => '06' . random_int(1000000, 9999999),
        'deja_bac' => true, 'annee_bac' => 2024,
        'serie_bac_id' => SerieBac::query()->first()->id,
        'etablissement_frequente' => 'X',
        'section_premier_choix_id' => Section::query()->first()->id,
        'statut' => $statut,
        'matricule_public' => 'CUK-' . strtoupper(\Illuminate\Support\Str::random(10)),
    ]);
}

function superAdminUser(): User
{
    $u = User::factory()->create();
    $u->roles()->syncWithoutDetaching([Role::query()->where('code', 'super-admin')->firstOrFail()->id]);

    return $u;
}

it('renders the candidat detail page and shows the annulment card for a PAID dossier', function (): void {
    $candidat = makeShowCandidat(Candidat::STATUS_VALID);

    $html = $this->actingAs(superAdminUser())->withoutMiddleware([EnsureTwoFactorVerified::class, EnsureActiveRole::class])
        ->get('/admin/concours/candidats/' . $candidat->getKey())
        ->assertOk()->getContent();

    expect($html)->toContain('Annuler ce dossier');
});

it('shows the annulment card for an ACCEPTED dossier awaiting payment', function (): void {
    // statut = 'oui' : accepté mais pas encore payé. Ce cas tombait entre les
    // deux cartes (Décision = 'non' seulement, Annulation = payé/admis), donc
    // plus aucune action n'était possible pour l'administration.
    $candidat = makeShowCandidat(Candidat::STATUS_OUI);

    // Rendu direct de la vue : on teste le gabarit, pas la résolution de route
    // (withoutMiddleware() désactive aussi SubstituteBindings, ce qui peut
    // livrer un modèle non résolu au contrôleur).
    $html = view('concours::admin.candidats.show', [
        'candidat'           => $candidat->load(['session', 'centre', 'nationalite', 'serieBac',
            'documents.documentRequis', 'motifsRejet.decidedBy', 'payments', 'modifications.user']),
        'expectedDocs'       => collect(),
        'canValidate'        => true,
        'canEdit'            => true,
        'canRejectValidated' => true,
        'sessionActive'      => true,
    ])->render();

    expect($html)->toContain('Annuler ce dossier')
        // …et le texte ne doit pas prétendre qu'un paiement a été encaissé.
        ->and($html)->toContain('encore été encaissé');
});

it('lets an admin annul an ACCEPTED dossier, and records the history', function (): void {
    $candidat = makeShowCandidat(Candidat::STATUS_OUI);
    $user     = superAdminUser();

    app(Modules\Concours\Services\CandidatValidationService::class)->decide(
        new Modules\Concours\DTOs\ValidationDecisionDto(
            candidatId:         (string) $candidat->getKey(),
            userId:             (string) $user->getKey(),
            decision:           'reject',
            motifs:             ['Dossier incomplet après acceptation'],
            canRejectValidated: true,   // super-admin / DG / DE
        ),
    );

    expect($candidat->refresh()->statut)->toBe(Candidat::STATUS_REJETE);

    // La transition reste tracée dans l'historique du dossier.
    $this->assertDatabaseHas('candidat_modifications', [
        'candidat_id' => $candidat->getKey(),
        'field'       => 'statut',
        'old_value'   => Candidat::STATUS_OUI,
        'new_value'   => Candidat::STATUS_REJETE,
    ]);
});

it('refuses to annul an ACCEPTED dossier without the elevated permission', function (): void {
    $candidat = makeShowCandidat(Candidat::STATUS_OUI);
    $user     = superAdminUser();

    app(Modules\Concours\Services\CandidatValidationService::class)->decide(
        new Modules\Concours\DTOs\ValidationDecisionDto(
            candidatId:         (string) $candidat->getKey(),
            userId:             (string) $user->getKey(),
            decision:           'reject',
            motifs:             ['Tentative sans droit'],
            canRejectValidated: false,  // ex. chef-centre
        ),
    );
})->throws(Modules\Concours\Exceptions\InvalidStatusTransitionException::class);

it('hides the annulment card for a dossier still under review', function (): void {
    $candidat = makeShowCandidat(Candidat::STATUS_NON);

    $html = $this->actingAs(superAdminUser())->withoutMiddleware([EnsureTwoFactorVerified::class, EnsureActiveRole::class])
        ->get('/admin/concours/candidats/' . $candidat->getKey())
        ->assertOk()->getContent();

    expect($html)->not->toContain('Annuler ce dossier');
});

it('assigns a centre-prefixed identifiant secret automatically on creation', function (): void {
    $candidat = makeShowCandidat(Candidat::STATUS_VALID);
    $prefix   = $candidat->centre->secretPrefix();

    expect($candidat->refresh()->identifiant_secret)->not->toBeNull()
        // e.g. LBV-000001 — 3 uppercase letters, dash, 6 digits.
        ->and($candidat->identifiant_secret)->toMatch('/^[A-Z0-9]{3}-\d{6}$/')
        ->and($candidat->identifiant_secret)->toStartWith($prefix . '-');
});

it('restarts the sequence inside each centre', function (): void {
    $centres = Modules\Concours\Models\Centre::query()->orderBy('nom')->take(2)->get();
    expect($centres)->toHaveCount(2);

    $a1 = makeShowCandidat(Candidat::STATUS_VALID, $centres[0]);
    $a2 = makeShowCandidat(Candidat::STATUS_VALID, $centres[0]);
    $b1 = makeShowCandidat(Candidat::STATUS_VALID, $centres[1]);

    // Two candidats of the same centre follow each other…
    expect($a2->refresh()->identifiant_secret)
        ->toBe($centres[0]->secretPrefix() . '-' . str_pad((string) ((int) substr((string) $a1->refresh()->identifiant_secret, -6) + 1), 6, '0', STR_PAD_LEFT))
        // …while another centre keeps its own numbering.
        ->and($b1->refresh()->identifiant_secret)->toStartWith($centres[1]->secretPrefix() . '-');
});
