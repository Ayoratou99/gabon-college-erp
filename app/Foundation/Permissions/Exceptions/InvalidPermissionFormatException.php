<?php

declare(strict_types=1);

namespace App\Foundation\Permissions\Exceptions;

use InvalidArgumentException;

final class InvalidPermissionFormatException extends InvalidArgumentException
{
    public static function wrongSegmentCount(string $pattern, int $actual): self
    {
        return new self(sprintf(
            'Permission "%s" must have exactly 3 segments separated by ":", got %d.',
            $pattern,
            $actual,
        ));
    }

    public static function invalidSegment(string $segmentName, string $value): self
    {
        return new self(sprintf(
            'Permission segment "%s" has invalid value "%s"; expected "*" or [a-z][a-z0-9_]*.',
            $segmentName,
            $value,
        ));
    }
}
