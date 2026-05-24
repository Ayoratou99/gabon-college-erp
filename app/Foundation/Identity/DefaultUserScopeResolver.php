<?php

declare(strict_types=1);

namespace App\Foundation\Identity;

use App\Foundation\Identity\Contracts\UserScopeResolver;
use App\Foundation\Permissions\Contracts\PermissionHolder;

/**
 * Default null-object implementation — returns empty arrays.
 *
 * Used when the Concours module isn't booted (e.g. early bootstrap, unit
 * tests). With this in place, `own_center` and `own_region` scopes safely
 * deny instead of crashing.
 */
final class DefaultUserScopeResolver implements UserScopeResolver
{
    public function accessibleCentreIds(PermissionHolder $user): array
    {
        return [];
    }

    public function accessibleRegionIds(PermissionHolder $user): array
    {
        return [];
    }
}
