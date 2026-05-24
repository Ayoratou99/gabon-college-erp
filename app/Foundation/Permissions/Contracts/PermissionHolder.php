<?php

declare(strict_types=1);

namespace App\Foundation\Permissions\Contracts;

use App\Foundation\Permissions\Permission;
use Illuminate\Support\Collection;

/**
 * Implemented by any actor (typically User) that can hold permissions.
 *
 * Three concerns live here so resolvers can stay generic:
 *   1. `permissions()`            — flat list of granted Permission value objects
 *   2. `accessibleCentreIds()`    — center scope membership (chef de centre)
 *   3. `accessibleRegionIds()`    — region scope membership
 *   4. `currentSessionId()`       — for `own_session` scope
 *
 * Implementing them as methods (not just relations) keeps the API stable
 * even if the underlying storage changes.
 */
interface PermissionHolder
{
    public function getKey(): mixed;

    /** @return Collection<int, Permission> */
    public function permissions(): Collection;

    /** @return array<int, string> */
    public function accessibleCentreIds(): array;

    /** @return array<int, string> */
    public function accessibleRegionIds(): array;

    public function currentSessionId(): ?string;
}
