<?php

declare(strict_types=1);

namespace App\Foundation\Permissions;

/**
 * Module-level registration of the permissions a module *exposes*.
 *
 * Each module's ServiceProvider declares its catalog so we can:
 *   - validate at boot that every permission string the app uses is
 *     declared somewhere (no silent typos);
 *   - render the role/permission editor UI in UserManagement;
 *   - autogenerate documentation.
 *
 *     $registry->declare('Concours', [
 *         'view:candidats:*',
 *         'view:candidats:own_center',
 *         'edit:candidats:*',
 *         'validate:candidats:own_center',
 *     ]);
 */
final class PermissionRegistry
{
    /** @var array<string, array<int, string>> module → permission strings */
    private array $catalog = [];

    /** @param array<int, string> $permissions */
    public function declare(string $module, array $permissions): void
    {
        $this->catalog[$module] = array_values(array_unique(array_merge(
            $this->catalog[$module] ?? [],
            $permissions,
        )));

        // Validate format eagerly so a typo blows up at boot, not in prod.
        foreach ($permissions as $perm) {
            Permission::parse($perm);
        }
    }

    /** @return array<string, array<int, string>> */
    public function all(): array
    {
        return $this->catalog;
    }

    /** @return array<int, string> */
    public function flat(): array
    {
        return array_values(array_unique(array_merge(...array_values($this->catalog))));
    }

    public function isDeclared(string $permission): bool
    {
        return in_array($permission, $this->flat(), true);
    }
}
