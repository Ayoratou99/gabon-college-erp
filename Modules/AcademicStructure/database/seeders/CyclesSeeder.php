<?php

declare(strict_types=1);

namespace Modules\AcademicStructure\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\AcademicStructure\Models\Cycle;

final class CyclesSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['code' => 'DUT',     'nom' => 'Cycle DUT',     'duree_annees' => 2, 'display_order' => 10],
            ['code' => 'LICENCE', 'nom' => 'Cycle Licence', 'duree_annees' => 3, 'display_order' => 20, 'active' => false],
            ['code' => 'MASTER',  'nom' => 'Cycle Master',  'duree_annees' => 2, 'display_order' => 30, 'active' => false],
        ];

        foreach ($rows as $row) {
            Cycle::query()->updateOrCreate(
                ['code' => $row['code']],
                $row + ['active' => $row['active'] ?? true],
            );
        }
    }
}
