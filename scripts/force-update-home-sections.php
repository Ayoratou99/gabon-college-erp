<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// One-shot force-update of site.home.sections because SettingsService::declare()
// preserves existing values (which is the right behaviour 99% of the time —
// admin overrides should never be silently clobbered). For the rich-content
// upgrade, we explicitly opt-in to overwrite here.

$service = app(\Modules\Parametrage\Services\SettingsService::class);

$service->set('site.home.sections', [
    ['title' => 'Verifier mon dossier',  'body' => 'Suivez en temps reel l etat de votre demande apres son depot.',                 'icon' => 'fas fa-search',      'cta' => 'Verifier',          'link' => '/verifier-demande',  'order' => 10],
    ['title' => 'Resultats du concours', 'body' => 'Consultez les listes d admis des leur publication officielle.',                  'icon' => 'fas fa-trophy',      'cta' => 'Voir les resultats', 'link' => '/resultats',         'order' => 20],
    ['title' => 'Modifier mon dossier',  'body' => 'Votre dossier a ete rejete ? Recuperez-le avec email + telephone pour le completer.', 'icon' => 'fas fa-edit',     'cta' => 'Modifier',           'link' => '/recuperer-dossier', 'order' => 30],
    ['title' => 'Procedure complete',    'body' => 'Documents requis, frais, dates cles — tout ce qu il faut savoir avant de postuler.', 'icon' => 'fas fa-info-circle', 'cta' => 'En savoir plus',     'link' => '/inscription',       'order' => 40],
]);

echo "site.home.sections forced to " . count((array) $service->get('site.home.sections')) . " entries.\n";
