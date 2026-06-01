<?php

declare(strict_types=1);

namespace Modules\Concours\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\AcademicStructure\Models\Cycle;
use Modules\Concours\Models\ConcoursSession;
use Modules\Concours\Models\Epreuve;
use Modules\Referentiels\Models\TypeEpreuve;

/**
 * Seeds a baseline set of epreuves for the active concours session:
 *  - 3 epreuves cycle-wide (Mathematics, Physics, Culture générale)
 *  - 1 epreuve per section can be added by the admin
 *
 * Re-running the seeder is idempotent (keyed on (session, code)).
 */
final class EpreuvesSeeder extends Seeder
{
    public function run(): void
    {
        $session = ConcoursSession::active();
        if ($session === null) {
            $this->command->warn('No active session — skipping EpreuvesSeeder.');
            return;
        }

        $dut = Cycle::query()->where('code', 'DUT')->first();
        if ($dut === null) {
            return;
        }

        $typeEcrit = TypeEpreuve::query()->where('code', 'ecrit')->first();
        if ($typeEcrit === null) {
            return;
        }

        // These baseline épreuves apply to every section of the DUT cycle. The
        // epreuve_sections pivot is the source of truth for eligibility, so we
        // sync the cycle's sections onto each épreuve below.
        $dutSectionIds = \Modules\AcademicStructure\Models\Section::query()
            ->where('cycle_id', $dut->id)
            ->pluck('id')
            ->all();

        $rows = [
            [
                'code'        => 'MATH-DUT',
                'libelle'     => 'Mathématiques',
                'coefficient' => 3.00,
                'duree'       => 180,
                'ordre'       => 10,
            ],
            [
                'code'        => 'PHYS-DUT',
                'libelle'     => 'Physique',
                'coefficient' => 2.00,
                'duree'       => 180,
                'ordre'       => 20,
            ],
            [
                'code'        => 'CG-DUT',
                'libelle'     => 'Culture générale',
                'coefficient' => 1.00,
                'duree'       => 60,
                'ordre'       => 30,
            ],
        ];

        foreach ($rows as $row) {
            $epreuve = Epreuve::query()->updateOrCreate(
                ['concours_session_id' => $session->id, 'code' => $row['code']],
                [
                    'type_epreuve_id' => $typeEcrit->id,
                    'libelle'         => $row['libelle'],
                    'scope_type'      => Epreuve::SCOPE_CYCLE, // legacy, kept for back-compat
                    'scope_id'        => $dut->id,
                    'coefficient'     => $row['coefficient'],
                    'duree_minutes'   => $row['duree'],
                    'note_max'        => 20.00,
                    'ordre'           => $row['ordre'],
                    'active'          => true,
                ],
            );
            $epreuve->sections()->sync($dutSectionIds);
        }
    }
}
