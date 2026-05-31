<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\AcademicStructure\Models\Section;
use Modules\Concours\Models\Centre;
use Modules\Concours\Models\ConcoursSession;
use Modules\Referentiels\Models\Nationalite;
use Modules\Referentiels\Models\SerieBac;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(Modules\Referentiels\Database\Seeders\ReferentielsDatabaseSeeder::class);
    $this->seed(Modules\AcademicStructure\Database\Seeders\AcademicStructureDatabaseSeeder::class);
    $this->seed(Modules\Concours\Database\Seeders\CentresSeeder::class);
    $this->seed(Modules\Concours\Database\Seeders\ConcoursSessionsSeeder::class);
    $this->seed(Modules\Parametrage\Database\Seeders\SettingsSeeder::class);

    // Make sure the active session has an open inscription window so the
    // wizard isn't slammed shut at the entry.
    $s = ConcoursSession::active();
    if ($s !== null) {
        $s->forceFill([
            'date_ouverture_inscriptions' => now()->subDay(),
            'date_fermeture_inscriptions' => now()->addMonth(),
        ])->save();
    }
});

it('entry redirects to the first step', function (): void {
    $this->get('/inscription')
        ->assertRedirect('/inscription/identite');
});

it('deep-linking past your progress bounces back', function (): void {
    // No draft yet → trying to land on /bac throws us back to /identite.
    $this->get('/inscription/bac')
        ->assertRedirect('/inscription/identite');
});

it('submitting step 1 advances to step 2 and saves to draft', function (): void {
    $nationaliteId = Nationalite::query()->first()->id;

    $this->post('/inscription/identite', [
        'nom'            => 'TEST',
        'prenom'         => 'User',
        'date_naissance' => '2000-01-15',
        'lieu_naissance' => 'Libreville',
        'sexe'           => 'M',
        'nationalite_id' => $nationaliteId,
    ])->assertRedirect('/inscription/contact');

    $this->get('/inscription/contact')
        ->assertOk()
        ->assertSee('Téléphone');
});

it('returns to a previous step without losing the draft', function (): void {
    $nationaliteId = Nationalite::query()->first()->id;

    $this->post('/inscription/identite', [
        'nom'            => 'TEST',
        'prenom'         => 'User',
        'date_naissance' => '2000-01-15',
        'lieu_naissance' => 'Libreville',
        'sexe'           => 'M',
        'nationalite_id' => $nationaliteId,
    ]);

    $this->post('/inscription/contact', [
        'email'     => 'wiz.' . random_int(1, 99999) . '@example.test',
        'telephone' => '+241 06 11 22 33',
    ])->assertRedirect('/inscription/bac');

    // Go back from /bac → /contact, the saved email should still be in the input.
    $this->post('/inscription/bac/back')->assertRedirect('/inscription/contact');
    $this->get('/inscription/contact')
        ->assertOk()
        ->assertSee('+241 06 11 22 33', escape: false);
});

it('rejects invalid step 1 data with a 422-equivalent flash', function (): void {
    $this->post('/inscription/identite', [
        'nom'            => '',          // required
        'prenom'         => '',          // required
        'date_naissance' => 'not-a-date',
        'sexe'           => 'X',          // not in M/F
    ])->assertSessionHasErrors(['nom', 'prenom', 'date_naissance', 'sexe', 'nationalite_id']);
});

it('reset clears the draft and redirects back to entry', function (): void {
    $nationaliteId = Nationalite::query()->first()->id;
    $this->post('/inscription/identite', [
        'nom' => 'X', 'prenom' => 'Y', 'date_naissance' => '2000-01-01',
        'lieu_naissance' => 'A', 'sexe' => 'M', 'nationalite_id' => $nationaliteId,
    ]);

    $this->post('/inscription/reset')->assertRedirect('/inscription');

    // After reset, deep-link gating kicks in again.
    $this->get('/inscription/contact')->assertRedirect('/inscription/identite');
});

it('rejects when inscriptions are closed', function (): void {
    ConcoursSession::active()?->forceFill([
        'date_fermeture_inscriptions' => now()->subDay(),
    ])->save();

    $this->get('/inscription')
        ->assertRedirect(route('concours.inscriptions.fermees'));
});

it('the legacy form route name still redirects to the wizard', function (): void {
    // Backwards compat: anything still linking concours.inscription.form
    // should NOT 404.
    $this->get(route('concours.inscription.form'))
        ->assertRedirect('/inscription');
});
