<?php

declare(strict_types=1);

namespace App\Foundation\Permissions\Resolvers;

use App\Foundation\Permissions\Contracts\PermissionHolder;
use App\Foundation\Permissions\Contracts\ScopeResolver;
use App\Foundation\Permissions\Permission;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Scope `*` — no restriction. Used for super-admin and "any-row" permissions.
 */
final class WildcardResolver implements ScopeResolver
{
    public function key(): string
    {
        return '*';
    }

    public function grants(PermissionHolder $holder, Permission $required, ?Model $target = null): bool
    {
        return true;
    }

    public function applyToQuery(Builder $query, PermissionHolder $holder, Permission $required): Builder
    {
        return $query;
    }
}
