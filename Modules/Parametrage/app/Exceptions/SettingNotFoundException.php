<?php

declare(strict_types=1);

namespace Modules\Parametrage\Exceptions;

use RuntimeException;

final class SettingNotFoundException extends RuntimeException
{
    public static function for(string $key): self
    {
        return new self("Aucun paramètre déclaré pour la clé « {$key} ».");
    }
}
