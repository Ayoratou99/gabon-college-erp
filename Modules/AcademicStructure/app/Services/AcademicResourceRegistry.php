<?php

declare(strict_types=1);

namespace Modules\AcademicStructure\Services;

use App\Foundation\Http\Contracts\ResourceRegistry;
use Illuminate\Database\Eloquent\Model;
use Modules\AcademicStructure\Models\AnneeAcademique;
use Modules\AcademicStructure\Models\Cycle;
use Modules\AcademicStructure\Models\Departement;
use Modules\AcademicStructure\Models\Faculte;
use Modules\AcademicStructure\Models\Niveau;
use Modules\AcademicStructure\Models\Salle;
use Modules\AcademicStructure\Models\Section;
use Modules\Referentiels\Concerns\ReferentielModel;

final class AcademicResourceRegistry implements ResourceRegistry
{
    /**
     * @var array<string, array{model: class-string<Model>, resource: string}>
     */
    private array $map = [
        'facultes'           => ['model' => Faculte::class,         'resource' => 'academic_facultes'],
        'departements'       => ['model' => Departement::class,     'resource' => 'academic_departements'],
        'cycles'             => ['model' => Cycle::class,           'resource' => 'academic_cycles'],
        'niveaux'            => ['model' => Niveau::class,          'resource' => 'academic_niveaux'],
        'sections'           => ['model' => Section::class,         'resource' => 'academic_sections'],
        'annees-academiques' => ['model' => AnneeAcademique::class, 'resource' => 'academic_annees'],
        'salles'             => ['model' => Salle::class,           'resource' => 'academic_salles'],
    ];

    public function slugs(): array
    {
        return array_keys($this->map);
    }

    public function modelFor(string $slug): string
    {
        return $this->map[$slug]['model']
            ?? throw new \InvalidArgumentException("Ressource académique inconnue : {$slug}.");
    }

    public function resourceFor(string $slug): string
    {
        return $this->map[$slug]['resource']
            ?? throw new \InvalidArgumentException("Ressource académique inconnue : {$slug}.");
    }

    /** @return array<string, list<string>> */
    public function rulesFor(string $slug): array
    {
        /** @var class-string<Model> $model */
        $model = $this->modelFor($slug);

        if (! in_array(ReferentielModel::class, class_uses_recursive($model), true)) {
            throw new \LogicException("{$model} doit utiliser le trait ReferentielModel.");
        }

        return $model::validationRules();
    }
}
