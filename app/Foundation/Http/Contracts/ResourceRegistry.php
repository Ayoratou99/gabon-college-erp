<?php

declare(strict_types=1);

namespace App\Foundation\Http\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * Implemented by per-module registries (Referentiels, AcademicStructure, ...)
 * to power the generic ResourceCrudController.
 *
 * Each registry maps a public URL slug ("nationalites", "cycles", ...) to:
 *   - the Eloquent model class
 *   - the RBAC resource segment used by the permission engine
 *   - the validation rule set the model declares
 *
 * Adding a new resource = one entry in the registry + a migration. No new
 * controller, no new route, no new form request needed for the standard
 * CRUD surface.
 */
interface ResourceRegistry
{
    /** @return list<string> */
    public function slugs(): array;

    /** @return class-string<Model> */
    public function modelFor(string $slug): string;

    /** RBAC resource segment, e.g. "referentiels_nationalites". */
    public function resourceFor(string $slug): string;

    /** @return array<string, list<string>> */
    public function rulesFor(string $slug): array;
}
