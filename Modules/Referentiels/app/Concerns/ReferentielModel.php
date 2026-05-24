<?php

declare(strict_types=1);

namespace Modules\Referentiels\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Shared behaviour for every referential model:
 *   - scopeActive() : where active = true
 *   - ordered()     : convenience sort by display_order then nom/libelle
 *
 * Plus a static `validationRules()` hook that the unified ReferentielController
 * looks up per resource type without needing per-controller validation classes.
 */
trait ReferentielModel
{
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        $label = $this->getLabelColumn();
        return $query->orderBy('display_order')->orderBy($label);
    }

    public function getLabelColumn(): string
    {
        // Most tables use "nom"; documents_requis / types_epreuves use "libelle".
        foreach (['libelle', 'nom'] as $col) {
            if (in_array($col, $this->getFillable(), true)) {
                return $col;
            }
        }
        return 'id';
    }

    /**
     * Subclasses override this to declare their fillable schema.
     *
     * @return array<string, list<string>>
     */
    abstract public static function validationRules(): array;
}
