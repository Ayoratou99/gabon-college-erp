<?php

declare(strict_types=1);

namespace Modules\AcademicStructure\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\AcademicStructure\Models\Faculte;

/**
 * CUK starts with a single faculty entry; the model supports more so a future
 * Faculté de Lettres / de Sciences split is a row away.
 */
final class FacultesSeeder extends Seeder
{
    public function run(): void
    {
        Faculte::query()->updateOrCreate(
            ['code' => 'CUK'],
            [
                'nom'           => 'Centre Universitaire de Koulamoutou',
                'description'   => 'Établissement de tutelle.',
                'active'        => true,
                'display_order' => 1,
            ],
        );
    }
}
