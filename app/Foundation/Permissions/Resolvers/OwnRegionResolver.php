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
 * Scope `own_region` — actor may act on rows whose region matches one of
 * their accessible regions. Useful for regional supervisors.
 */
final class OwnRegionResolver implements ScopeResolver
{
    public function key(): string
    {
        return 'own_region';
    }

    public function grants(PermissionHolder $holder, Permission $required, ?Model $target = null): bool
    {
        $accessible = $holder->accessibleRegionIds();
        if ($accessible === []) {
            return false;
        }
        if ($target === null) {
            return true;
        }
        if (! $target instanceof Scopable) {
            return false;
        }
        $column = $target->scopeColumnFor('own_region');
        if ($column === null) {
            return false;
        }

        return in_array((string) $target->getAttribute($column), array_map('strval', $accessible), true);
    }

    public function applyToQuery(Builder $query, PermissionHolder $holder, Permission $required): Builder
    {
        $model = $query->getModel();
        $accessible = $holder->accessibleRegionIds();

        if (! $model instanceof Scopable || $accessible === []) {
            return $query->whereRaw('1 = 0');
        }

        $column = $model->scopeColumnFor('own_region');
        if ($column === null) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn($model->qualifyColumn($column), $accessible);
    }
}
