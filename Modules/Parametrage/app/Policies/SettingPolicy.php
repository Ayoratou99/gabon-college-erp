<?php

declare(strict_types=1);

namespace Modules\Parametrage\Policies;

use Modules\Parametrage\Models\Setting;
use Modules\UserManagement\Models\User;

/**
 * The Gate::before hook in FoundationServiceProvider already accepts
 * `$user->can('edit:parametrage:*', $setting)` as a string permission, so
 * this policy mainly exists to (a) make the intent explicit in code and
 * (b) layer per-category nuance ("only super-admin may touch eBilling
 * secrets") on top of the generic RBAC grant.
 */
final class SettingPolicy
{
    public function view(User $user, Setting $setting): bool
    {
        if ($setting->is_public) {
            return true;
        }
        return $user->can('view:parametrage:*', $setting);
    }

    public function update(User $user, Setting $setting): bool
    {
        // Secrets (encrypted settings) are restricted to super-admin even if
        // the generic edit permission is granted to DG/DE.
        if ($setting->is_encrypted && ! $user->hasRole('super-admin')) {
            return false;
        }
        return $user->can('edit:parametrage:*', $setting);
    }
}
