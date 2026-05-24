<?php

declare(strict_types=1);

namespace Modules\Referentiels\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Referentiels\Models\Province;

final class ProvincesSeeder extends Seeder
{
    public function run(): void
    {
        // Order matches legacy idprov so anyone cross-referencing the old DB
        // gets a predictable sort.
        $rows = [
            ['code' => 'Est', 'nom' => 'Estuaire',         'display_order' => 1],
            ['code' => 'HOg', 'nom' => 'Haut-Ogooué',      'display_order' => 2],
            ['code' => 'MOg', 'nom' => 'Moyen-Ogooué',     'display_order' => 3],
            ['code' => 'Ngo', 'nom' => 'Ngounié',          'display_order' => 4],
            ['code' => 'Nya', 'nom' => 'Nyanga',           'display_order' => 5],
            ['code' => 'OIv', 'nom' => 'Ogooué-Ivindo',    'display_order' => 6],
            ['code' => 'OLo', 'nom' => 'Ogooué-Lolo',      'display_order' => 7],
            ['code' => 'OMa', 'nom' => 'Ogooué-Maritime',  'display_order' => 8],
            ['code' => 'WNt', 'nom' => 'Woleu-Ntem',       'display_order' => 9],
        ];

        foreach ($rows as $row) {
            Province::query()->updateOrCreate(['code' => $row['code']], $row + ['active' => true]);
        }
    }
}
