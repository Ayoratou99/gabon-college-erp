<?php

declare(strict_types=1);

namespace App\Foundation\Permissions;

use App\Foundation\Permissions\Contracts\PermissionHolder;
use App\Foundation\Permissions\Exceptions\InvalidPermissionFormatException;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\Model;

/**
 * The single source of truth for "can this actor do this thing?".
 *
 * The check walks every permission held by the actor, finds those whose
 * (action, resource) segments cover the requirement, then asks the matching
 * scope resolver whether the *scope* segment grants the operation.
 *
 * First grant wins. No grant means denied.
 *
 * Events:
 *   - `permission.denied`  fired when no granted permission satisfies the request;
 *     useful for audit / observability. Failures are *not* fired for unauthenticated
 *     calls (those short-circuit before any check).
 */
final class PermissionChecker
{
    public function __construct(
        private readonly ScopeResolverRegistry $resolvers,
        private readonly Dispatcher $events,
    ) {}

    /**
     * @param  PermissionHolder|null  $holder    null = unauthenticated, always denied
     * @param  string                  $required  e.g. "edit:candidats:any" — the scope is the *target* scope,
     *                                           used here only for symmetry; actual scope evaluation comes
     *                                           from the granted permission, not the requested one.
     * @param  Model|null              $target    optional target row for per-row scope checks
     */
    public function can(?PermissionHolder $holder, string $required, ?Model $target = null): bool
    {
        if ($holder === null) {
            return false;
        }

        try {
            $req = Permission::parse($required);
        } catch (InvalidPermissionFormatException $e) {
            // Surface programmer errors fast in dev; deny in production.
            if (function_exists('app') && app()->hasDebugModeEnabled()) {
                throw $e;
            }
            return false;
        }

        foreach ($holder->permissions() as $granted) {
            if (! $granted->coversActionAndResource($req)) {
                continue;
            }

            $resolver = $this->resolvers->get($granted->scope);
            if ($resolver->grants($holder, $req, $target)) {
                return true;
            }
        }

        $this->events->dispatch('permission.denied', [
            'holder_id' => $holder->getKey(),
            'required'  => $required,
            'target'    => $target?->getKey(),
        ]);

        return false;
    }

    public function cannot(?PermissionHolder $holder, string $required, ?Model $target = null): bool
    {
        return ! $this->can($holder, $required, $target);
    }
}
