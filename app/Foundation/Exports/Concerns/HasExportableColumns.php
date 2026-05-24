<?php

declare(strict_types=1);

namespace App\Foundation\Exports\Concerns;

/**
 * Implemented by every model that participates in the unified export pipeline.
 *
 * `exportColumns()` returns the declarative column catalog the ExportBuilder
 * consumes. Keeping the declaration on the model means controllers don't
 * need to repeat it across xlsx / csv / pdf endpoints.
 *
 *   public static function exportColumns(): array {
 *       return [
 *           ['header' => 'Matricule',  'accessor' => 'matricule_public'],
 *           ['header' => 'Nom',        'accessor' => 'nom'],
 *           ['header' => 'Centre',     'accessor' => fn ($c) => $c->centre?->nom],
 *           ['header' => 'Moyenne',    'accessor' => 'moyenne', 'format' => 'decimal'],
 *           ['header' => 'Inscrit le', 'accessor' => 'created_at', 'format' => 'datetime'],
 *       ];
 *   }
 */
trait HasExportableColumns
{
    /**
     * @return list<array<string, mixed>>
     */
    abstract public static function exportColumns(): array;

    /**
     * Relations to eager-load before iterating rows (avoids N+1 during export).
     *
     * @return list<string>
     */
    public static function exportRelations(): array
    {
        return [];
    }
}
