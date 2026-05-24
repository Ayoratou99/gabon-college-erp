<?php

declare(strict_types=1);

namespace Modules\AcademicStructure\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\AcademicStructure\Models\AnneeAcademique;

final class AnneesAcademiquesSeeder extends Seeder
{
    public function run(): void
    {
        // First clear any existing "courante" flag so the partial unique index
        // is happy when we set the new current year below.
        DB::transaction(function (): void {
            AnneeAcademique::query()->update(['est_courante' => false]);

            $rows = [
                [
                    'code'          => '2024-2025',
                    'date_debut'    => '2024-10-01',
                    'date_fin'      => '2025-09-30',
                    'statut'        => 'terminee',
                    'est_courante'  => false,
                    'display_order' => 10,
                ],
                [
                    'code'          => '2025-2026',
                    'date_debut'    => '2025-10-01',
                    'date_fin'      => '2026-09-30',
                    'statut'        => 'en_cours',
                    'est_courante'  => true,
                    'display_order' => 20,
                ],
                [
                    'code'          => '2026-2027',
                    'date_debut'    => '2026-10-01',
                    'date_fin'      => '2027-09-30',
                    'statut'        => 'a_venir',
                    'est_courante'  => false,
                    'display_order' => 30,
                ],
            ];

            foreach ($rows as $row) {
                AnneeAcademique::query()->updateOrCreate(['code' => $row['code']], $row);
            }
        });
    }
}
