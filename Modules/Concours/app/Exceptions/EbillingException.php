<?php

declare(strict_types=1);

namespace Modules\Concours\Exceptions;

use RuntimeException;

final class EbillingException extends RuntimeException
{
    public static function configurationMissing(string $key): self
    {
        return new self("Configuration eBilling manquante : {$key}.");
    }

    public static function invoiceCreationFailed(int $httpStatus, ?string $body): self
    {
        return new self("Création de la facture eBilling échouée (HTTP {$httpStatus}): " . ($body ?? '(no body)'));
    }

    public static function invalidSignature(): self
    {
        return new self('Signature HMAC du callback eBilling invalide.');
    }
}
