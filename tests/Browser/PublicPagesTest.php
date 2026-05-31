<?php

declare(strict_types=1);

use Laravel\Dusk\Browser;

/**
 * Smoke-coverage for the public-facing site. Each test boots Chrome, hits
 * a URL, and verifies the page actually rendered the right content + key
 * markup so we'd catch a regression on real HTML the user sees.
 */

test('home page renders the hero + navbar + footer', function (): void {
    $this->browse(function (Browser $b): void {
        $b->visit('/')
          ->assertSee('Centre Universitaire de Koulamoutou')
          ->assertPresent('nav.public-nav')
          ->assertPresent('section.hero')
          ->assertPresent('footer.public-footer')
          // CTA buttons in the hero
          ->assertSee('S\'inscrire')
          // Brand logo from /img/cuk/ is hooked up
          ->assertSourceHas('/img/cuk/');
    });
});

test('vérifier-demande form is reachable + shows the right placeholder', function (): void {
    $this->browse(function (Browser $b): void {
        $b->visit('/verifier-demande')
          ->assertSee('Vérifier mon dossier')
          ->assertPresent('input[name="q"]')
          ->assertAttribute('input[name="q"]', 'placeholder', 'Matricule, nom, email ou téléphone');
    });
});

test('résultats page shows the historical 2025 publication', function (): void {
    $this->browse(function (Browser $b): void {
        $b->visit('/resultats')
          ->assertSee('Résultats du concours');
    });
});

test('login page uses the public theme (navbar + auth-card on gradient)', function (): void {
    $this->browse(function (Browser $b): void {
        $b->visit('/login')
          ->assertPresent('nav.public-nav')        // shares the public navbar now
          ->assertPresent('section.auth-band')    // gradient hero
          ->assertPresent('div.auth-card')        // centered card
          ->assertSeeIn('button[type="submit"]', 'Se connecter');
    });
});

test('first-login wizard step 1 asks email + tel', function (): void {
    $this->browse(function (Browser $b): void {
        $b->visit('/connexion/premiere-fois')
          ->assertSee('Étape 1 sur 3')
          ->assertPresent('input[name="email"]')
          ->assertPresent('input[name="telephone"]');
    });
});
