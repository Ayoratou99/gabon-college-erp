<?php

declare(strict_types=1);

namespace Modules\Parametrage\Database\Seeders;

use Illuminate\Database\Seeder;

final class ParametrageDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([SettingsSeeder::class]);
    }
}
