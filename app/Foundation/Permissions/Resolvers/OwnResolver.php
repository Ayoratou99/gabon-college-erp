<?php

declare(strict_types=1);

namespace App\Foundation\Permissions\Resolvers;

use App\Foundation\Permissions\Contracts\PermissionHolder;
use App\Foundation\Permissions\Contracts\Scopable;
use App\Foundation\Permissions\Contracts\ScopeResolver;
use App\Foundation\Permissions\Permission;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Scope `own` — the actor may act only on rows they own.
 *
 * The model decides what "own" means via Scopable::scopeColumnFor('own'),
 * which returns the FK column (typically `user_id`).
 *
 * Without a target (e.g. for a list view), the query is filtered by that
 * column. With a target, the row's column value is compared to the actor's key.
 */
final class OwnResolver implements ScopeResolver
{
    public function key(): string
    {
        return 'own';
    }

    public function grants(PermissionHolder $holder, Permission $required, ?Model $target = null): bool
    {
        if ($target === null) {
            // Generic capability check (e.g. for menu rendering). Granted.
            return true;
        }

        if (! $target instanceof Scopable) {
            return false;
        }

        $column = $target->scopeColumnFor('own');
        if ($column === null) {
            return false;
        }

        return (string) $target->getAttribute($column) === (string) $holder->getKey();
    }

    public function applyToQuery(Builder $query, PermissionHolder $holder, Permission $required): Builder
    {
        $model = $query->getModel();

        if (! $model instanceof Scopable) {
            return $query->whereRaw('1 = 0');
        }

        $column = $model->scopeColumnFor('own');
        if ($column === null) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where($model->qualifyColumn($column), $holder->getKey());
    }
}
