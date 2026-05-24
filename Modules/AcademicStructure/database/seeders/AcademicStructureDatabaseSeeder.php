<?php

declare(strict_types=1);

namespace Modules\AcademicStructure\Database\Seeders;

use Illuminate\Database\Seeder;

final class AcademicStructureDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Dependency order: Faculte → Departement (FK), Cycle → Niveau (FK),
        // Cycle + Departement → Section (FK), then standalone Annees + Salles.
        $this->call([
            FacultesSeeder::class,
            DepartementsSeeder::class,
            CyclesSeeder::class,
            NiveauxSeeder::class,
            SectionsSeeder::class,
            AnneesAcademiquesSeeder::class,
            SallesSeeder::class,
        ]);
    }
}
