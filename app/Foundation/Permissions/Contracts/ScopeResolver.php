<?php

declare(strict_types=1);

namespace App\Foundation\Permissions\Contracts;

use App\Foundation\Permissions\Permission;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Resolves a single scope segment (e.g. `own_center`).
 *
 * Two operations:
 *   - `grants()`        : evaluates whether the holder may act on a *single* target row
 *   - `applyToQuery()`  : narrows a list query to the rows the holder may see
 *
 * Both operations must be consistent: any row that `applyToQuery()` keeps
 * must also be granted by `grants()` (and vice-versa). This invariant is
 * tested in ScopedQueryTest.
 */
interface ScopeResolver
{
    /** The scope key this resolver handles, e.g. `own_center`. */
    public function key(): string;

    /**
     * @param  Model|null  $target  null when checking generic capability
     *                              (e.g. "can list", not "can edit row 42")
     */
    public function grants(PermissionHolder $holder, Permission $required, ?Model $target = null): bool;

    /**
     * Apply a WHERE-clause that restricts the given query to rows the
     * holder may see under this scope. Must return a safe-by-default
     * `1 = 0` filter when the model does not support this scope.
     */
    public function applyToQuery(Builder $query, PermissionHolder $holder, Permission $required): Builder;
}
