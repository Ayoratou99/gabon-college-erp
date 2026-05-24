<?php

declare(strict_types=1);

namespace Modules\Referentiels\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Referentiels\Models\TypeEpreuve;

final class TypesEpreuvesSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['code' => 'ecrit',     'libelle' => 'Épreuve écrite',     'modalite' => 'ecrit',
             'duree_minutes_defaut' => 180, 'coefficient_defaut' => 2.00, 'display_order' => 10],
            ['code' => 'qcm',       'libelle' => 'QCM',                'modalite' => 'ecrit',
             'duree_minutes_defaut' => 60,  'coefficient_defaut' => 1.00, 'display_order' => 20],
            ['code' => 'oral',      'libelle' => 'Épreuve orale',      'modalite' => 'oral',
             'duree_minutes_defaut' => 30,  'coefficient_defaut' => 1.00, 'display_order' => 30],
            ['code' => 'pratique',  'libelle' => 'Épreuve pratique',   'modalite' => 'pratique',
             'duree_minutes_defaut' => 120, 'coefficient_defaut' => 2.00, 'display_order' => 40],
            ['code' => 'entretien', 'libelle' => 'Entretien de motivation', 'modalite' => 'oral',
             'duree_minutes_defaut' => 20,  'coefficient_defaut' => 1.00, 'display_order' => 50],
        ];

        foreach ($rows as $row) {
            TypeEpreuve::query()->updateOrCreate(['code' => $row['code']], $row + ['active' => true]);
        }
    }
}
