<?php

declare(strict_types=1);

namespace Modules\AcademicStructure\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\AcademicStructure\Models\Cycle;
use Modules\AcademicStructure\Models\Niveau;

final class NiveauxSeeder extends Seeder
{
    public function run(): void
    {
        $dut = Cycle::query()->where('code', 'DUT')->firstOrFail();
        $licence = Cycle::query()->where('code', 'LICENCE')->firstOrFail();
        $master = Cycle::query()->where('code', 'MASTER')->firstOrFail();

        $niveaux = [
            // DUT
            ['cycle_id' => $dut->id, 'code' => 'DUT1', 'libelle' => 'DUT 1ère année', 'ordre' => 1, 'est_niveau_entree' => true,  'display_order' => 10],
            ['cycle_id' => $dut->id, 'code' => 'DUT2', 'libelle' => 'DUT 2ème année', 'ordre' => 2, 'est_niveau_entree' => false, 'display_order' => 20],
            // Licence (cycle not active yet but niveaux pre-declared)
            ['cycle_id' => $licence->id, 'code' => 'L1', 'libelle' => 'Licence 1', 'ordre' => 1, 'est_niveau_entree' => true,  'display_order' => 30, 'active' => false],
            ['cycle_id' => $licence->id, 'code' => 'L2', 'libelle' => 'Licence 2', 'ordre' => 2, 'est_niveau_entree' => false, 'display_order' => 40, 'active' => false],
            ['cycle_id' => $licence->id, 'code' => 'L3', 'libelle' => 'Licence 3', 'ordre' => 3, 'est_niveau_entree' => false, 'display_order' => 50, 'active' => false],
            // Master
            ['cycle_id' => $master->id, 'code' => 'M1', 'libelle' => 'Master 1', 'ordre' => 1, 'est_niveau_entree' => true,  'display_order' => 60, 'active' => false],
            ['cycle_id' => $master->id, 'code' => 'M2', 'libelle' => 'Master 2', 'ordre' => 2, 'est_niveau_entree' => false, 'display_order' => 70, 'active' => false],
        ];

        foreach ($niveaux as $row) {
            Niveau::query()->updateOrCreate(
                ['cycle_id' => $row['cycle_id'], 'code' => $row['code']],
                $row + ['active' => $row['active'] ?? true],
            );
        }
    }
}
