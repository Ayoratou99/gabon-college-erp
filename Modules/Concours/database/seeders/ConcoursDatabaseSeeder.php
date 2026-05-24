<?php

declare(strict_types=1);

namespace Modules\Concours\Database\Seeders;

use Illuminate\Database\Seeder;

final class ConcoursDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            CentresSeeder::class,
            ConcoursSessionsSeeder::class,
            ChefCentreAssignmentsSeeder::class,
            EpreuvesSeeder::class,
        ]);
    }
}
