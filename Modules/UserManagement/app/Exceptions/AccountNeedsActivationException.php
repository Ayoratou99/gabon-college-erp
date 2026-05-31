<?php

declare(strict_types=1);

namespace Modules\UserManagement\Exceptions;

use RuntimeException;

/**
 * Thrown by AuthenticationService when the identifier maps to a user that
 * still needs to complete the first-login wizard (password not set yet).
 * The controller catches this and redirects to the activation flow.
 */
final class AccountNeedsActivationException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Ce compte étudiant n\'a pas encore été activé.');
    }
}
