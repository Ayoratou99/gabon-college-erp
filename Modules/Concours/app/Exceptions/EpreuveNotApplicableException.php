<?php

declare(strict_types=1);

namespace Modules\Concours\Exceptions;

use RuntimeException;

final class EpreuveNotApplicableException extends RuntimeException
{
    public static function for(string $epreuveId, string $candidatId): self
    {
        return new self("L'épreuve {$epreuveId} ne s'applique pas au candidat {$candidatId}.");
    }
}
