<?php

declare(strict_types=1);

namespace Modules\Referentiels\Services;

use App\Foundation\Http\Contracts\ResourceRegistry;
use Illuminate\Database\Eloquent\Model;
use Modules\Referentiels\Concerns\ReferentielModel;
use Modules\Referentiels\Models\DocumentRequis;
use Modules\Referentiels\Models\Nationalite;
use Modules\Referentiels\Models\Province;
use Modules\Referentiels\Models\SerieBac;
use Modules\Referentiels\Models\TypeEpreuve;

/**
 * Maps a public URL slug to its Eloquent model + RBAC resource segment
 * for the unified ReferentielController (extends ResourceCrudController).
 *
 * Adding a new referential: append one entry to $map + create the migration
 * + model + seeder. No controller / route / form-request changes needed.
 */
final class ReferentielRegistry implements ResourceRegistry
{
    /**
     * @var array<string, array{model: class-string<Model>, resource: string}>
     */
    private array $map = [
        'provinces'    => ['model' => Province::class,       'resource' => 'referentiels_provinces'],
        'nationalites' => ['model' => Nationalite::class,    'resource' => 'referentiels_nationalites'],
        'series-bac'   => ['model' => SerieBac::class,       'resource' => 'referentiels_series_bac'],
        'documents'    => ['model' => DocumentRequis::class, 'resource' => 'referentiels_documents'],
        'epreuves'     => ['model' => TypeEpreuve::class,    'resource' => 'referentiels_epreuves'],
    ];

    public function slugs(): array
    {
        return array_keys($this->map);
    }

    public function modelFor(string $slug): string
    {
        return $this->map[$slug]['model']
            ?? throw new \InvalidArgumentException("Référentiel inconnu : {$slug}.");
    }

    public function resourceFor(string $slug): string
    {
        return $this->map[$slug]['resource']
            ?? throw new \InvalidArgumentException("Référentiel inconnu : {$slug}.");
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
