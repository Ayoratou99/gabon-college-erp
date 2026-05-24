<?php

declare(strict_types=1);

namespace Modules\UserManagement\Exceptions;

use RuntimeException;

final class RecaptchaFailedException extends RuntimeException
{
    public function __construct(
        public readonly ?float $score = null,
        public readonly ?string $reason = null,
    ) {
        parent::__construct(
            $reason ?? 'Vérification anti-robot échouée. Rechargez la page et réessayez.'
        );
    }
}
