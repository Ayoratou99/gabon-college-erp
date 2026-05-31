<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$counts = [
    'Nationalités'      => \Modules\Referentiels\Models\Nationalite::count(),
    'Provinces'         => \Modules\Referentiels\Models\Province::count(),
    'Séries BAC'        => \Modules\Referentiels\Models\SerieBac::count(),
    'Documents requis'  => \Modules\Referentiels\Models\DocumentRequis::count(),
    'Types d\'épreuves' => \Modules\Referentiels\Models\TypeEpreuve::count(),
    'Facultés'          => \Modules\AcademicStructure\Models\Faculte::count(),
    'Départements'      => \Modules\AcademicStructure\Models\Departement::count(),
    'Cycles'            => \Modules\AcademicStructure\Models\Cycle::count(),
    'Niveaux'           => \Modules\AcademicStructure\Models\Niveau::count(),
    'Sections'          => \Modules\AcademicStructure\Models\Section::count(),
    'Années académ.'    => \Modules\AcademicStructure\Models\AnneeAcademique::count(),
    'Salles'            => \Modules\AcademicStructure\Models\Salle::count(),
    'Centres'           => \Modules\Concours\Models\Centre::count(),
    'Concours sessions' => \Modules\Concours\Models\ConcoursSession::count(),
    'Épreuves'          => \Modules\Concours\Models\Epreuve::count(),
    'Rôles'             => \Modules\UserManagement\Models\Role::count(),
    'Permissions'       => \Modules\UserManagement\Models\Permission::count(),
    'Settings'          => \Modules\Parametrage\Models\Setting::count(),
];

foreach ($counts as $k => $v) {
    printf("%-22s %s\n", $k, $v);
}

$fee = app(\Modules\Parametrage\Services\SettingsService::class)->get('concours.fee.amount');
printf("\nFee setting (concours.fee.amount): %s FCFA\n", $fee);
$active = \Modules\Concours\Models\ConcoursSession::active();
printf("Active concours session: %s (%s)\n", $active?->code ?? '(none)', $active?->libelle ?? '');
