<?php

declare(strict_types=1);

namespace Modules\Referentiels\Database\Seeders;

use Illuminate\Database\Seeder;

final class ReferentielsDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            ProvincesSeeder::class,
            NationalitesSeeder::class,
            SeriesBacSeeder::class,
            DocumentsRequisSeeder::class,
            TypesEpreuvesSeeder::class,
        ]);
    }
}
