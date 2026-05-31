<?php

declare(strict_types=1);

namespace Modules\UserManagement\Exceptions;

use RuntimeException;

/**
 * Thrown by AuthenticationService when the resolved user is currently
 * blocked (users.blocked_at IS NOT NULL). The controller catches this and
 * surfaces a flash error to the login form. We do NOT burn a throttle slot
 * for blocked accounts — we'd rather make the block obvious to legitimate
 * users than rate-limit them out of asking an admin to lift it.
 */
final class AccountBlockedException extends RuntimeException
{
    public function __construct(?string $reason = null)
    {
        $message = 'Votre compte est bloqué. Contactez un administrateur.';
        if ($reason !== null && $reason !== '') {
            $message .= ' Motif&nbsp;: ' . $reason;
        }
        parent::__construct($message);
    }
}
