<?php

declare(strict_types=1);

namespace Modules\Concours\Exceptions;

use RuntimeException;

final class InscriptionsClosedException extends RuntimeException
{
    public static function noActiveSession(): self
    {
        return new self('Aucun concours n\'est actuellement ouvert aux inscriptions.');
    }

    public static function outsideDateRange(): self
    {
        return new self('Les inscriptions sont fermées pour ce concours.');
    }
}
