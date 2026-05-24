<?php

declare(strict_types=1);

namespace Modules\Concours\Http\Controllers\Admin;

use App\Foundation\Permissions\PermissionChecker;
use App\Foundation\Permissions\ScopedQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Concours\Http\Requests\SaveNotesBatchRequest;
use Modules\Concours\Models\Candidat;
use Modules\Concours\Models\Epreuve;
use Modules\Concours\Models\Note;
use Modules\Concours\Services\MoyenneCalculatorService;
use Modules\Concours\Services\NoteService;
use Symfony\Component\HttpFoundation\Response;

/**
 * Note workflow:
 *
 *   GET  /api/admin/concours/notes/grid?epreuve_id=X
 *        → list of (candidat, current note) for the chef's centre, ready to
 *          render as an editable grid.
 *
 *   POST /api/admin/concours/notes/batch
 *        → save many notes at once (one epreuve, multiple candidats); the
 *          service validates eligibility per row.
 *
 *   POST /api/admin/concours/notes/recompute
 *        → recompute moyenne + rang for every paid candidate of a session.
 *          Cheap (a few hundred rows) so it can be triggered after each
 *          batch save.
 */
final class NoteController extends Controller
{
    public function __construct(
        private readonly NoteService $notes,
        private readonly MoyenneCalculatorService $moyenne,
        private readonly ScopedQuery $scoped,
        private readonly PermissionChecker $checker,
    ) {}

    public function grid(Request $request): JsonResponse
    {
        $epreuveId = (string) $request->string('epreuve_id')->toString();
        if ($epreuveId === '') {
            abort(422, 'epreuve_id requis');
        }
        $epreuve = Epreuve::query()->findOrFail($epreuveId);

        if (! $this->checker->can($request->user(), 'enter:notes:*')
            && ! $this->checker->can($request->user(), 'enter:notes:own_center')) {
            abort(403);
        }

        // Scope candidates to chef's centre when applicable.
        $query = $epreuve->eligibleCandidatsQuery();
        $query = $this->scoped->apply($query, $request->user(), 'view', 'candidats');

        $candidats = $query
            ->where('statut', '!=', Candidat::STATUS_REJETE)
            ->orderBy('nom')->orderBy('prenom')
            ->get(['id', 'nom', 'prenom', 'matricule_public', 'centre_id']);

        $existing = Note::query()
            ->where('epreuve_id', $epreuve->getKey())
            ->whereIn('candidat_id', $candidats->pluck('id'))
            ->get()
            ->keyBy('candidat_id');

        return response()->json([
            'epreuve'   => $epreuve->only(['id', 'code', 'libelle', 'note_max', 'coefficient']),
            'candidats' => $candidats->map(fn ($c) => [
                'id'               => $c->id,
                'nom'              => $c->nom,
                'prenom'           => $c->prenom,
                'matricule_public' => $c->matricule_public,
                'note'             => $existing->get($c->id)?->only(['valeur', 'absent', 'locked', 'commentaire']),
            ])->values(),
        ]);
    }

    public function saveBatch(SaveNotesBatchRequest $request): JsonResponse
    {
        $count = $this->notes->saveBatch($request->toDto(
            userId: (string) $request->user()->getAuthIdentifier(),
        ));

        return response()->json(['saved' => $count]);
    }

    public function recompute(Request $request): JsonResponse
    {
        if (! $this->checker->can($request->user(), 'publish:results:*')) {
            abort(403);
        }
        $sessionId = (string) $request->string('concours_session_id')->toString();
        $stats = $this->moyenne->recomputeForSession($sessionId);
        return response()->json($stats);
    }
}
