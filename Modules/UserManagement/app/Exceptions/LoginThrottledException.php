<?php

declare(strict_types=1);

namespace Modules\UserManagement\Exceptions;

use RuntimeException;

final class LoginThrottledException extends RuntimeException
{
    public function __construct(
        public readonly int $retryAfterSeconds,
        public readonly string $tier,
    ) {
        parent::__construct(sprintf(
            'Trop de tentatives échouées (%s). Réessayez dans %d secondes.',
            $tier,
            $retryAfterSeconds,
        ));
    }
}
