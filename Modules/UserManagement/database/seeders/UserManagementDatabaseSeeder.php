<?php

declare(strict_types=1);

namespace Modules\UserManagement\Database\Seeders;

use Illuminate\Database\Seeder;

final class UserManagementDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            LegacyAdminImportSeeder::class,
        ]);
    }
}
