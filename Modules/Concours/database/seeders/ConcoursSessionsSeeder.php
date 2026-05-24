<?php

declare(strict_types=1);

namespace Modules\Concours\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\AcademicStructure\Models\AnneeAcademique;
use Modules\Concours\Models\Centre;
use Modules\Concours\Models\ConcoursSession;

/**
 * Seeds the 2025-2026 concours session and binds every active centre to it.
 * Lieu-de-concours strings come from the legacy `centres.lieuconcour` values.
 */
final class ConcoursSessionsSeeder extends Seeder
{
    public function run(): void
    {
        $annee = AnneeAcademique::query()->where('code', '2025-2026')->first();
        if ($annee === null) {
            $this->command->warn('AnneeAcademique 2025-2026 not seeded — skipping ConcoursSessionsSeeder.');
            return;
        }

        DB::transaction(function () use ($annee): void {
            ConcoursSession::query()->where('est_active', true)->update(['est_active' => false]);

            $session = ConcoursSession::query()->updateOrCreate(
                ['code' => 'CONCOURS-2025'],
                [
                    'annee_academique_id'         => $annee->id,
                    'libelle'                     => 'Concours d\'entrée — session 2025',
                    'date_ouverture_inscriptions' => '2025-07-15',
                    'date_fermeture_inscriptions' => '2025-08-13',
                    'date_concours'               => '2025-08-14',
                    'statut'                      => 'inscriptions_fermees',
                    'est_active'                  => true,
                ],
            );

            $venuePerCode = [
                'CENTRE-LBV' => 'ENSET',
                'CENTRE-FCV' => 'USTM',
                'CENTRE-LBA' => 'Lycée Charles MEFANE',
                'CENTRE-MOU' => 'Lycée J.-J. BOUCAVEL',
                'CENTRE-TCH' => 'Lycée Nazaire BOULINGUI',
                'CENTRE-MAK' => 'Lycée Alexandre SAMBA',
                'CENTRE-KOU' => 'Lycée Paul KOUYA',
                'CENTRE-POG' => 'Lycée Joseph AMBOUROUET AVARO',
                'CENTRE-OYE' => 'Lycée Richard NGUEMA BEKALE',
            ];

            $centresByCode = Centre::query()->whereIn('code', array_keys($venuePerCode))->get()->keyBy('code');

            foreach ($venuePerCode as $code => $venue) {
                $centre = $centresByCode->get($code);
                if ($centre === null) {
                    continue;
                }
                $session->centres()->syncWithoutDetaching([
                    $centre->id => [
                        'lieu_concours'     => $venue,
                        'capacite_override' => null,
                        'active'            => true,
                    ],
                ]);
            }
        });
    }
}
