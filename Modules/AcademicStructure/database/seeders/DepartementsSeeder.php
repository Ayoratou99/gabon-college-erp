<?php

declare(strict_types=1);

namespace Modules\AcademicStructure\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\AcademicStructure\Models\Departement;
use Modules\AcademicStructure\Models\Faculte;

/**
 * Departments derived from the 7 historical sections (legacy `sections` table
 * stored mixed concerns; we split formation ↔ département cleanly here).
 */
final class DepartementsSeeder extends Seeder
{
    public function run(): void
    {
        $cuk = Faculte::query()->where('code', 'CUK')->firstOrFail();

        $rows = [
            ['code' => 'DEPT-CHIM', 'nom' => 'Génie Chimique',                     'display_order' => 10],
            ['code' => 'DEPT-CIV',  'nom' => 'Génie Civil',                        'display_order' => 20],
            ['code' => 'DEPT-THER', 'nom' => 'Génie Thermique et Énergies',        'display_order' => 30],
            ['code' => 'DEPT-MECA', 'nom' => 'Génie Mécanique',                    'display_order' => 40],
            ['code' => 'DEPT-INFO', 'nom' => 'Informatique et Communication',      'display_order' => 50],
            ['code' => 'DEPT-BIO',  'nom' => 'Biologie / Biochimie',               'display_order' => 60],
            ['code' => 'DEPT-BIOM', 'nom' => 'Génie Biomédical',                   'display_order' => 70],
        ];

        foreach ($rows as $row) {
            Departement::query()->updateOrCreate(
                ['code' => $row['code']],
                $row + ['faculte_id' => $cuk->id, 'active' => true],
            );
        }
    }
}
