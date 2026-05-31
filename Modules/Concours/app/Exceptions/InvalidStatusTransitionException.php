<?php

declare(strict_types=1);

namespace Modules\Concours\Exceptions;

use RuntimeException;

/**
 * Raised when the admin tries to perform a state transition that the
 * business rules forbid:
 *
 *   - rejecting a dossier whose statut is no longer "non"
 *   - rejecting once the session inscription window has closed
 *
 * Wrapped by the controller into a 422 response so the UI can surface
 * the message inline rather than the user seeing a 500.
 */
final class InvalidStatusTransitionException extends RuntimeException
{
    public static function rejectOnlyFromPending(string $current): self
    {
        return new self(
            "Impossible de rejeter ce dossier : son statut est « {$current} ». "
            . 'Seuls les dossiers en cours de traitement peuvent être rejetés.'
        );
    }

    public static function sessionInscriptionClosed(): self
    {
        return new self(
            'Les inscriptions de cette session sont closes — '
            . 'aucune décision de rejet ne peut plus être enregistrée.'
        );
    }
}
