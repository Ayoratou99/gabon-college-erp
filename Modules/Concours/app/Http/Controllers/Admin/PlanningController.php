<?php

declare(strict_types=1);

namespace Modules\Concours\Http\Controllers\Admin;

use App\Foundation\Permissions\PermissionChecker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Concours\Http\Concerns\GuardsArchivedSession;
use Modules\Concours\Http\Requests\SchedulePlanningRequest;
use Modules\Concours\Models\Epreuve;
use Modules\Concours\Models\EpreuvePlanning;
use Modules\Concours\Services\PlanningService;
use Symfony\Component\HttpFoundation\Response;

final class PlanningController extends Controller
{
    use GuardsArchivedSession;

    public function __construct(
        private readonly PlanningService $planning,
        private readonly PermissionChecker $checker,
    ) {}

    public function index(Request $request): JsonResponse
    {
        if (! $this->checker->can($request->user(), 'view:planning:*')
            && ! $this->checker->can($request->user(), 'view:planning:own_center')) {
            abort(403);
        }

        $query = EpreuvePlanning::query()
            ->with(['epreuve:id,code,libelle,coefficient', 'salle:id,nom,capacite']);

        if ($sessionId = $request->string('session_id')->toString()) {
            $query->whereHas('epreuve', fn ($q) => $q->where('concours_session_id', $sessionId));
        }
        if ($centreSessionId = $request->string('concours_session_centre_id')->toString()) {
            $query->where('concours_session_centre_id', $centreSessionId);
        }

        return response()->json(
            $query->orderBy('date_epreuve')->orderBy('heure_debut')->get(),
        );
    }

    public function store(SchedulePlanningRequest $request): JsonResponse
    {
        // Planning rows attach to an épreuve, which carries the session id.
        // Block scheduling against an archived session — there's no legitimate
        // reason to rewrite the timetable of a past concours.
        $epreuveId = (string) $request->validated('epreuve_id', '');
        if ($epreuveId !== '') {
            $epreuve = Epreuve::query()->find($epreuveId);
            $this->assertSessionEditable($epreuve?->session, 'le planning');
        }

        $result = $this->planning->schedule($request->toDto());

        return response()->json([
            'planning'  => $result['planning'],
            'conflicts' => $result['conflicts'],
        ], $result['conflicts']->isEmpty() ? Response::HTTP_CREATED : Response::HTTP_CONFLICT);
    }

    public function destroy(Request $request, EpreuvePlanning $planning): Response
    {
        if (! $this->checker->can($request->user(), 'manage:planning:*')) {
            abort(403);
        }
        $planning->loadMissing('epreuve.session');
        $this->assertSessionEditable($planning->epreuve?->session, 'ce créneau de planning');
        $planning->delete();
        return response()->noContent();
    }
}
