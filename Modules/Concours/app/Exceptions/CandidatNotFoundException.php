<?php

declare(strict_types=1);

namespace Modules\Concours\Exceptions;

use RuntimeException;

final class CandidatNotFoundException extends RuntimeException
{
    public static function forLookup(): self
    {
        return new self('Aucun dossier ne correspond à ces informations.');
    }
}
