<?php

declare(strict_types=1);

namespace Modules\Referentiels\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Referentiels\Models\DocumentRequis;

/**
 * Seeded from the legacy `documents` table. The old app accepted whatever
 * extension the candidate uploaded; we now declare a closed set per document
 * type so the upload middleware can validate strictly.
 */
final class DocumentsRequisSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            [
                'code'    => 'acte',
                'libelle' => 'Copie légalisée de l\'acte de naissance',
                'description' => 'Document délivré par la mairie et légalisé.',
                'formats_acceptes' => ['pdf', 'jpg', 'jpeg', 'png'],
                'taille_max_ko'  => 5120,
                'obligatoire'    => true,
                'display_order'  => 10,
            ],
            [
                'code'    => 'atsctle',
                'libelle' => 'Attestation de scolarité',
                'description' => 'Pour les candidats préparant le baccalauréat (scolarité de terminale).',
                'formats_acceptes' => ['pdf', 'jpg', 'jpeg', 'png'],
                'taille_max_ko'  => 5120,
                'obligatoire'    => false,
                'display_order'  => 20,
            ],
            [
                'code'    => 'colebac',
                'libelle' => 'Copie légalisée ou attestation de réussite du baccalauréat',
                'description' => 'Pour les candidats ayant déjà obtenu le baccalauréat.',
                'formats_acceptes' => ['pdf', 'jpg', 'jpeg', 'png'],
                'taille_max_ko'  => 5120,
                'obligatoire'    => false,
                'display_order'  => 30,
            ],
            [
                'code'    => 'coledip',
                'libelle' => 'Bulletins des trois trimestres de terminale',
                'description' => 'Photo ou scan des trois bulletins.',
                'formats_acceptes' => ['pdf', 'jpg', 'jpeg', 'png'],
                'taille_max_ko'  => 10240,
                'obligatoire'    => false,
                'display_order'  => 40,
            ],
            [
                'code'    => 'rnbac',
                'libelle' => 'Relevé de notes du baccalauréat',
                'description' => 'Relevé délivré par le ministère / établissement.',
                'formats_acceptes' => ['pdf', 'jpg', 'jpeg', 'png'],
                'taille_max_ko'  => 5120,
                'obligatoire'    => false,
                'display_order'  => 50,
            ],
        ];

        foreach ($rows as $row) {
            DocumentRequis::query()->updateOrCreate(['code' => $row['code']], $row + ['active' => true]);
        }
    }
}
