<?php

declare(strict_types=1);

namespace Modules\AcademicStructure\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\AcademicStructure\Models\Cycle;
use Modules\AcademicStructure\Models\Departement;
use Modules\AcademicStructure\Models\Section;

/**
 * The 7 DUT formations from the legacy `sections` table, each routed to its
 * matching département so the admission UI can group sections by domaine.
 */
final class SectionsSeeder extends Seeder
{
    public function run(): void
    {
        $dut = Cycle::query()->where('code', 'DUT')->firstOrFail();
        $deptByCode = Departement::query()->pluck('id', 'code')->all();

        $rows = [
            ['code' => 'CI',  'nom' => 'DUT Chimie Industrielle',                  'dept' => 'DEPT-CHIM', 'display_order' => 10],
            ['code' => 'AEC', 'nom' => 'DUT Architecture et Éco-construction',    'dept' => 'DEPT-CIV',  'display_order' => 20],
            ['code' => 'GTE', 'nom' => 'DUT Génie Thermique et Énergies',         'dept' => 'DEPT-THER', 'display_order' => 30],
            ['code' => 'PM',  'nom' => 'DUT Productique Mécanique',               'dept' => 'DEPT-MECA', 'display_order' => 40],
            ['code' => 'IC',  'nom' => 'DUT en Informatique et Communication',    'dept' => 'DEPT-INFO', 'display_order' => 50],
            ['code' => 'ABB', 'nom' => 'DUT Analyses Biologiques et Biochimiques','dept' => 'DEPT-BIO',  'display_order' => 60],
            ['code' => 'MEB', 'nom' => 'DUT Maintenance des Équipements Biomédicaux','dept' => 'DEPT-BIOM','display_order' => 70],
        ];

        foreach ($rows as $row) {
            Section::query()->updateOrCreate(
                ['code' => $row['code']],
                [
                    'cycle_id'           => $dut->id,
                    'departement_id'     => $deptByCode[$row['dept']] ?? null,
                    'nom'                => $row['nom'],
                    'places_par_session' => 50,
                    'ouvert_au_concours' => true,
                    'active'             => true,
                    'display_order'      => $row['display_order'],
                ],
            );
        }
    }
}
