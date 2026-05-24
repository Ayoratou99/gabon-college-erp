<?php

declare(strict_types=1);

namespace App\Foundation\Exports\Exceptions;

use RuntimeException;

final class ExportFormatNotSupportedException extends RuntimeException
{
    /** @param list<string> $supported */
    public static function for(string $format, array $supported): self
    {
        return new self(sprintf(
            'Format d\'export « %s » non supporté. Formats disponibles : %s.',
            $format,
            implode(', ', $supported),
        ));
    }
}
