<?php

declare(strict_types=1);

namespace Modules\Referentiels\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Referentiels\Models\SerieBac;

final class SeriesBacSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['code' => 'C',     'nom' => 'Série C',     'display_order' => 1],
            ['code' => 'D',     'nom' => 'Série D',     'display_order' => 2],
            ['code' => 'SI',    'nom' => 'Série SI',    'display_order' => 3],
            ['code' => 'CLPI',  'nom' => 'Série CLPI',  'display_order' => 4],
            ['code' => 'MI',    'nom' => 'Série MI',    'display_order' => 5],
            ['code' => 'ERE',   'nom' => 'Série ERE',   'display_order' => 6],
            ['code' => 'PM',    'nom' => 'Série PM',    'display_order' => 7],
            ['code' => 'AEC',   'nom' => 'Série AEC',   'display_order' => 8],
            ['code' => 'F1',    'nom' => 'Série F1',    'display_order' => 9],
            ['code' => 'F2',    'nom' => 'Série F2',    'display_order' => 10],
            ['code' => 'F3',    'nom' => 'Série F3',    'display_order' => 11],
            ['code' => 'F4',    'nom' => 'Série F4',    'display_order' => 12],
            ['code' => 'F1D',   'nom' => 'Série F1D',   'display_order' => 13],
            ['code' => 'MEH',   'nom' => 'Maintenance des Équipements Hospitaliers', 'display_order' => 14],
            ['code' => 'AUTRE', 'nom' => 'Autre',       'display_order' => 99],
        ];

        foreach ($rows as $row) {
            SerieBac::query()->updateOrCreate(['code' => $row['code']], $row + ['active' => true]);
        }
    }
}
