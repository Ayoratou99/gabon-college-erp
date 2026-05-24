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
 * Scope `own_session` — actor may act on rows that belong to the *current*
 * concours session (i.e. the session they're currently working on).
 *
 * The session ID is supplied by the PermissionHolder, typically derived
 * from the active context in UserManagement once auth picks the
 * working session at login.
 */
final class OwnSessionResolver implements ScopeResolver
{
    public function key(): string
    {
        return 'own_session';
    }

    public function grants(PermissionHolder $holder, Permission $required, ?Model $target = null): bool
    {
        $sessionId = $holder->currentSessionId();
        if ($sessionId === null) {
            return false;
        }
        if ($target === null) {
            return true;
        }
        if (! $target instanceof Scopable) {
            return false;
        }
        $column = $target->scopeColumnFor('own_session');
        if ($column === null) {
            return false;
        }

        return (string) $target->getAttribute($column) === (string) $sessionId;
    }

    public function applyToQuery(Builder $query, PermissionHolder $holder, Permission $required): Builder
    {
        $model = $query->getModel();
        $sessionId = $holder->currentSessionId();

        if (! $model instanceof Scopable || $sessionId === null) {
            return $query->whereRaw('1 = 0');
        }

        $column = $model->scopeColumnFor('own_session');
        if ($column === null) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where($model->qualifyColumn($column), $sessionId);
    }
}
