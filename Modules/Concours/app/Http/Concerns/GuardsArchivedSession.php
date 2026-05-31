<?php

declare(strict_types=1);

namespace Modules\Concours\Http\Concerns;

use Modules\Concours\Models\ConcoursSession;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Shared session-archive guard for back-office controllers.
 *
 * Mutations on Concours data — épreuves, plannings, chef-centre assignments,
 * centres-per-session pivot, notes, selection runs — are forbidden once the
 * underlying session is archived (results published OR statut=clos). A session
 * is NOT frozen merely because the concours date has passed.
 *
 * Resolution can come from either:
 *   - a concrete ConcoursSession (`assertSessionEditable($session)`), or
 *   - a session id pulled from the request / model (`assertSessionIdEditable($id)`).
 *
 * The guard throws 409 Conflict with a French message so the UI can surface
 * the reason directly. View-layer code should additionally hide the action
 * affordances by checking `$session?->isEditable()` (or the `$sessionEditable`
 * boolean each page controller passes through), to avoid presenting dead
 * buttons in the first place.
 */
trait GuardsArchivedSession
{
    /**
     * Refuse the action when the session is archived. Pass `null` to fall
     * back to whatever the controller has stamped as the current target —
     * we treat "no session at all" as also blocking, since there's nothing
     * legitimate to mutate.
     */
    protected function assertSessionEditable(?ConcoursSession $session, string $what = 'cette donnée'): void
    {
        if ($session === null) {
            throw new HttpException(
                409,
                "Aucune session active sélectionnée — impossible de modifier {$what}.",
            );
        }
        if (! $session->isEditable()) {
            throw new HttpException(
                409,
                "La session « {$session->libelle} » est archivée — {$what} n'est plus modifiable.",
            );
        }
    }

    /**
     * Convenience overload when only the session id is in scope (e.g. inside
     * a JSON store endpoint where the body carries `concours_session_id`).
     */
    protected function assertSessionIdEditable(?string $sessionId, string $what = 'cette donnée'): void
    {
        $session = $sessionId !== null && $sessionId !== ''
            ? ConcoursSession::query()->find($sessionId)
            : null;
        $this->assertSessionEditable($session, $what);
    }
}
