<?php

declare(strict_types=1);

namespace Modules\AcademicStructure\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\AcademicStructure\Models\Salle;

/**
 * Seeds a handful of representative rooms so the back-office isn't empty on
 * day one. Real rooms are added by the admin via the CRUD UI (or imported
 * from a CSV in a later stage).
 */
final class SallesSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['code' => 'AMPHI-A', 'nom' => 'Amphithéâtre A', 'capacite' => 250, 'type' => 'amphi',  'batiment' => 'Bâtiment principal', 'etage' => 'RDC', 'accessible_pmr' => true,  'display_order' => 10],
            ['code' => 'AMPHI-B', 'nom' => 'Amphithéâtre B', 'capacite' => 180, 'type' => 'amphi',  'batiment' => 'Bâtiment principal', 'etage' => '1',   'accessible_pmr' => false, 'display_order' => 20],
            ['code' => 'SALLE-101', 'nom' => 'Salle 101',    'capacite' => 40,  'type' => 'salle',  'batiment' => 'Bâtiment principal', 'etage' => '1',   'display_order' => 30],
            ['code' => 'SALLE-102', 'nom' => 'Salle 102',    'capacite' => 40,  'type' => 'salle',  'batiment' => 'Bâtiment principal', 'etage' => '1',   'display_order' => 40],
            ['code' => 'LABO-INFO', 'nom' => 'Labo Info',    'capacite' => 30,  'type' => 'labo',   'batiment' => 'Bâtiment IT',        'etage' => 'RDC', 'display_order' => 50],
            ['code' => 'LABO-CHIM', 'nom' => 'Labo Chimie',  'capacite' => 25,  'type' => 'labo',   'batiment' => 'Bâtiment Sciences',  'etage' => 'RDC', 'display_order' => 60],
        ];

        foreach ($rows as $row) {
            Salle::query()->updateOrCreate(['code' => $row['code']], $row + ['active' => true]);
        }
    }
}
