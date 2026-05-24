<?php

declare(strict_types=1);

namespace Modules\Concours\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Concours\Models\Centre;
use Modules\Referentiels\Models\Province;

/**
 * The 9 Gabonese centres of the legacy app, referenced once and reused
 * across concours sessions. Per-session details (capacité override, lieu
 * de concours physique) live on concours_session_centres.
 */
final class CentresSeeder extends Seeder
{
    public function run(): void
    {
        $province = Province::query()->pluck('id', 'code');

        $rows = [
            ['code' => 'CENTRE-LBV',  'nom' => 'Libreville',      'ville' => 'Libreville',  'province' => 'Est', 'display_order' => 1],
            ['code' => 'CENTRE-FCV',  'nom' => 'Franceville',     'ville' => 'Franceville', 'province' => 'HOg', 'display_order' => 2],
            ['code' => 'CENTRE-LBA',  'nom' => 'Lambaréné',       'ville' => 'Lambaréné',   'province' => 'MOg', 'display_order' => 3],
            ['code' => 'CENTRE-MOU',  'nom' => 'Mouila',          'ville' => 'Mouila',      'province' => 'Ngo', 'display_order' => 4],
            ['code' => 'CENTRE-TCH',  'nom' => 'Tchibanga',       'ville' => 'Tchibanga',   'province' => 'Nya', 'display_order' => 5],
            ['code' => 'CENTRE-MAK',  'nom' => 'Makokou',         'ville' => 'Makokou',     'province' => 'OIv', 'display_order' => 6],
            ['code' => 'CENTRE-KOU',  'nom' => 'Koula-Moutou',    'ville' => 'Koula-Moutou','province' => 'OLo', 'display_order' => 7],
            ['code' => 'CENTRE-POG',  'nom' => 'Port-Gentil',     'ville' => 'Port-Gentil', 'province' => 'OMa', 'display_order' => 8],
            ['code' => 'CENTRE-OYE',  'nom' => 'Oyem',            'ville' => 'Oyem',        'province' => 'WNt', 'display_order' => 9],
        ];

        foreach ($rows as $row) {
            Centre::query()->updateOrCreate(
                ['code' => $row['code']],
                [
                    'nom'                 => $row['nom'],
                    'ville'               => $row['ville'],
                    'province_id'         => $province->get($row['province']),
                    'capacite_par_defaut' => 200,
                    'active'              => true,
                    'display_order'       => $row['display_order'],
                ],
            );
        }
    }
}
