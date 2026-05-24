<?php

declare(strict_types=1);

namespace Modules\Concours\Exceptions;

use RuntimeException;

final class SelectionAlreadyPublishedException extends RuntimeException
{
    public static function for(string $sessionId): self
    {
        return new self("Les résultats de la session {$sessionId} sont déjà publiés.");
    }
}
