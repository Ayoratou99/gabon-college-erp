<?php

declare(strict_types=1);

namespace Modules\Concours\Http\Controllers\Admin;

use App\Foundation\Permissions\PermissionChecker;
use App\Foundation\Permissions\ScopedQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Concours\Http\Requests\ValidationDecisionRequest;
use Modules\Concours\Models\Candidat;
use Modules\Concours\Models\ConcoursSession;
use Modules\Concours\Services\CandidatValidationService;

/**
 * Back-office API around candidats.
 *
 *   GET  /api/admin/candidats           paginated, scope-filtered by RBAC
 *   GET  /api/admin/candidats/{c}       single
 *   POST /api/admin/candidats/{c}/decide
 *                                        accept / reject (with motifs[])
 *
 * The scope filter is the ScopedQuery applied to the index query — a
 * chef-centre only ever sees candidats whose `centre_id` is in their
 * accessibleCentreIds(), DE/DG see everything, candidats see only their own.
 */
final class CandidatController extends Controller
{
    public function __construct(
        private readonly ScopedQuery $scoped,
        private readonly PermissionChecker $checker,
        private readonly CandidatValidationService $validator,
    ) {}

    public function index(Request $request): JsonResponse
    {
        if (! $this->checker->can($request->user(), 'view:candidats:*')) {
            abort(403);
        }

        $query = Candidat::query()
            ->with(['centre:id,nom', 'premierChoix:id,nom', 'session:id,code']);

        $query = $this->scoped->apply($query, $request->user(), 'view', 'candidats');

        if ($status = $request->string('statut')->toString()) {
            $query->where('statut', $status);
        }
        if ($sessionId = $request->string('session_id')->toString()) {
            $query->where('concours_session_id', $sessionId);
        } else {
            $active = ConcoursSession::active();
            if ($active !== null) {
                $query->where('concours_session_id', $active->getKey());
            }
        }
        if ($search = $request->string('search')->toString()) {
            $query->where(function ($q) use ($search): void {
                $q->where('nom', 'ilike', "%{$search}%")
                  ->orWhere('prenom', 'ilike', "%{$search}%")
                  ->orWhere('matricule_public', 'ilike', "%{$search}%")
                  ->orWhere('email', 'ilike', "%{$search}%");
            });
        }

        $perPage = min(max((int) $request->integer('per_page', 50), 1), 200);
        return response()->json($query->orderByDesc('created_at')->paginate($perPage));
    }

    public function show(Request $request, Candidat $candidat): JsonResponse
    {
        if (! $this->checker->can($request->user(), 'view:candidats:*', $candidat)) {
            abort(403);
        }
        $candidat->load([
            'session:id,code,date_concours',
            'centre',
            'nationalite',
            'serieBac',
            'premierChoix',
            'secondChoix',
            'documents.documentRequis',
            'motifsRejet.decidedBy:id,nom,prenom',
            'payments',
        ]);
        return response()->json($candidat);
    }

    public function decide(ValidationDecisionRequest $request, Candidat $candidat): JsonResponse
    {
        $result = $this->validator->decide($request->toDto(
            candidatId: $candidat->getKey(),
            userId:     (string) $request->user()->getAuthIdentifier(),
        ));

        return response()->json([
            'id'     => $result->getKey(),
            'statut' => $result->statut,
        ]);
    }
}
