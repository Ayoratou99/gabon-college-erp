<?php

declare(strict_types=1);

namespace Modules\UserManagement\Exceptions;

use RuntimeException;

class AuthenticationException extends RuntimeException
{
    public static function invalidCredentials(): self
    {
        return new self('Identifiants invalides.');
    }

    public static function accountDisabled(): self
    {
        return new self('Ce compte est désactivé.');
    }
}
