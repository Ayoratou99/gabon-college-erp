<?php

declare(strict_types=1);

namespace Modules\Parametrage\Exceptions;

use RuntimeException;

final class InvalidSettingValueException extends RuntimeException
{
    public static function wrongType(string $key, string $expectedType, mixed $given): self
    {
        $gotType = get_debug_type($given);
        return new self("Le paramètre « {$key} » attend un type {$expectedType}, reçu {$gotType}.");
    }

    public static function unknownType(string $type): self
    {
        return new self("Type de paramètre inconnu : « {$type} ».");
    }

    public static function validationFailed(string $key, string $message): self
    {
        return new self("Le paramètre « {$key} » est invalide : {$message}");
    }
}
