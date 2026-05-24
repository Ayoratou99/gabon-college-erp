<?php

declare(strict_types=1);

namespace App\Foundation\Identity\Contracts;

use App\Foundation\Permissions\Contracts\PermissionHolder;

/**
 * Resolves the scope-membership of an authenticated actor.
 *
 * The User model itself lives in UserManagement and cannot know about
 * concours-specific concepts (centres, sessions). It delegates to whichever
 * implementation of this contract is bound in the container — by default
 * the no-op DefaultUserScopeResolver (returns empty arrays), overridden
 * by Concours::CandidatCentreResolver once that module boots.
 *
 * This keeps the dependency direction clean:
 *
 *   UserManagement (Foundation) ←── Concours
 *   (no UserManagement → Concours import ever)
 */
interface UserScopeResolver
{
    /** @return array<int, string> centre UUIDs the user has access to */
    public function accessibleCentreIds(PermissionHolder $user): array;

    /** @return array<int, string> region UUIDs the user has access to */
    public function accessibleRegionIds(PermissionHolder $user): array;
}
