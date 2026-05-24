<?php

declare(strict_types=1);

namespace App\Foundation\Permissions;

use App\Foundation\Permissions\Contracts\PermissionHolder;
use Illuminate\Database\Eloquent\Builder;

/**
 * Turns a permission into a WHERE-clause on a list query.
 *
 * The actor may hold *multiple* permissions that cover the same
 * (action, resource) — for instance `view:candidats:own` AND
 * `view:candidats:own_center`. In that case the resulting query
 * is the UNION of what every resolver would let through, i.e. the
 * clauses are OR-ed together inside a nested WHERE.
 *
 * If no permission covers the request, the query is forced to
 * return zero rows (`WHERE 1 = 0`). Never silently widens.
 */
final class ScopedQuery
{
    public function __construct(
        private readonly ScopeResolverRegistry $resolvers,
    ) {}

    public function apply(
        Builder $query,
        PermissionHolder $holder,
        string $action,
        string $resource,
    ): Builder {
        $matching = $holder->permissions()
            ->filter(fn (Permission $p) =>
                ($p->action === '*' || $p->action === $action)
                && ($p->resource === '*' || $p->resource === $resource)
            )
            ->values();

        if ($matching->isEmpty()) {
            return $query->whereRaw('1 = 0');
        }

        // Short-circuit: any wildcard scope = no restriction.
        if ($matching->contains(fn (Permission $p) => $p->isWildcardScope())) {
            return $query;
        }

        // Build a single nested OR-block covering every scope the actor has.
        $required = new Permission($action, $resource, '*');

        return $query->where(function (Builder $inner) use ($matching, $holder, $required): void {
            foreach ($matching as $granted) {
                $resolver = $this->resolvers->get($granted->scope);
                $inner->orWhere(function (Builder $branch) use ($resolver, $holder, $required): void {
                    $resolver->applyToQuery($branch, $holder, $required);
                });
            }
        });
    }
}
