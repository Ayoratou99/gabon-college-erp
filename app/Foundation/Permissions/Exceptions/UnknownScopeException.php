<?php

declare(strict_types=1);

namespace App\Foundation\Permissions\Exceptions;

use RuntimeException;

final class UnknownScopeException extends RuntimeException
{
    /** @param array<int, string> $registered */
    public static function for(string $scope, array $registered): self
    {
        return new self(sprintf(
            'No ScopeResolver registered for scope "%s". Registered scopes: %s.',
            $scope,
            $registered === [] ? '(none)' : implode(', ', $registered),
        ));
    }
}
