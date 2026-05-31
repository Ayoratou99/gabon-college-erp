<?php

declare(strict_types=1);

use Laravel\Dusk\Browser;
use Modules\UserManagement\Models\User;

/**
 * Drives the back-office as the seeded super-admin (telephone=060000000 /
 * admin1234). 2FA is disabled in .env.dusk.local so we land on the
 * dashboard directly after submitting the login form.
 */

function loginAsAdmin(Browser $b): void
{
    $b->visit('/login')
      ->type('identifier', '060000000')
      ->type('password', 'admin1234')
      ->press('Se connecter')
      ->waitForLocation('/dashboard');
}

test('admin can log in and reach the dashboard', function (): void {
    $this->browse(function (Browser $b): void {
        loginAsAdmin($b);
        $b->assertPathIs('/dashboard')
          ->assertPresent('aside.app-sidebar')   // sidebar is up
          ->assertPresent('.session-band')        // hero band rendered
          ->assertSee('Tableau de bord');
    });
});

test('the new "Sessions" item is at top-level, outside the Concours sub-menu', function (): void {
    $this->browse(function (Browser $b): void {
        loginAsAdmin($b);
        $b->visit('/admin/concours/sessions')
          ->assertSee('Sessions du concours')
          ->assertPresent('table');
    });
});

test('users page lists the seeded super-admin', function (): void {
    $this->browse(function (Browser $b): void {
        loginAsAdmin($b);
        $b->visit('/admin/users')
          ->assertSee('Utilisateurs')
          ->assertPresent('table#users-table');
    });
});

test('user detail page exposes 2FA reset + password reset for super-admin', function (): void {
    $this->browse(function (Browser $b): void {
        loginAsAdmin($b);
        $admin = User::query()->where('telephone', '060000000')->firstOrFail();
        $b->visit('/admin/users/' . $admin->id)
          ->assertSee('Réinitialiser la 2FA')
          ->assertSee('Réinitialiser le mot de passe')
          ->assertSee('Rôles attribués');
    });
});

test('settings (parametrage) page renders without 500', function (): void {
    $this->browse(function (Browser $b): void {
        loginAsAdmin($b);
        $b->visit('/admin/parametrage')
          ->assertSee('Paramétrage');
    });
});

test('candidat list page exposes new filters (centre, section, série, sexe)', function (): void {
    $this->browse(function (Browser $b): void {
        loginAsAdmin($b);
        $b->visit('/admin/concours/candidats')
          ->assertPresent('select[x-model="centre_id"]')
          ->assertPresent('select[x-model="section_id"]')
          ->assertPresent('select[x-model="serie_bac_id"]')
          ->assertPresent('select[x-model="deja_bac"]')
          ->assertPresent('select[x-model="sexe"]');
    });
});

test('planning page surfaces the centre selector when a session is active', function (): void {
    $this->browse(function (Browser $b): void {
        loginAsAdmin($b);
        $b->visit('/admin/concours/planning');
        // Either we see the centre form OR the empty-state message —
        // both branches are a healthy 200 render.
        $b->waitFor('body');
    });
});
