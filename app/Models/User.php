<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Compatibility alias.
 *
 * Laravel's default auth provider points at `App\Models\User`. The real model
 * lives in the UserManagement module so it can own its migrations, factories,
 * and policies. This thin subclass lets us keep config/auth.php untouched.
 */
final class User extends \Modules\UserManagement\Models\User
{
}
