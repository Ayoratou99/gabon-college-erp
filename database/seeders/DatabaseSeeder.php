<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\AcademicStructure\Database\Seeders\AcademicStructureDatabaseSeeder;
use Modules\Concours\Database\Seeders\ConcoursDatabaseSeeder;
use Modules\Parametrage\Database\Seeders\ParametrageDatabaseSeeder;
use Modules\Referentiels\Database\Seeders\ReferentielsDatabaseSeeder;
use Modules\UserManagement\Database\Seeders\UserManagementDatabaseSeeder;

final class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            // FK-safe order:
            //   1. Referentiels      — provinces, nationalités, bacs, documents, types d'épreuves.
            //   2. AcademicStructure — facultés/dépts/cycles/niveaux/sections/années/salles.
            //   3. UserManagement    — roles + permissions + legacy admin import.
            //   4. Parametrage       — settings store (uses user_id FK for audit).
            //   5. Concours          — centres + sessions + chef-centre assignments.
            ReferentielsDatabaseSeeder::class,
            AcademicStructureDatabaseSeeder::class,
            UserManagementDatabaseSeeder::class,
            ParametrageDatabaseSeeder::class,
            ConcoursDatabaseSeeder::class,
        ]);
    }
}
