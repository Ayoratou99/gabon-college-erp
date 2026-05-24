<?php

declare(strict_types=1);

namespace Modules\Concours\Http\Controllers\Admin\Pages;

use App\Foundation\Exports\ExportBuilder;
use App\Foundation\Permissions\PermissionChecker;
use App\Foundation\Permissions\ScopedQuery;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Concours\Models\Candidat;
use Modules\Concours\Models\Centre;
use Modules\Concours\Models\ConcoursSession;
use Symfony\Component\HttpFoundation\Response;

/**
 * Server-rendered candidat list + detail + exports.
 *
 * The list and the export endpoint share the same query-building logic via
 * `buildIndexQuery()` so a filtered Excel/PDF export always mirrors what's
 * on screen.
 */
final class CandidatPageController extends Controller
{
    public function __construct(
        private readonly ScopedQuery $scoped,
        private readonly PermissionChecker $checker,
    ) {}

    public function index(Request $request): View
    {
        if (! $this->checker->can($request->user(), 'view:candidats:*')) {
            abort(Response::HTTP_FORBIDDEN);
        }

        $session = ConcoursSession::active();

        $candidats = $this->buildIndexQuery($request, $session)
            ->with(['centre:id,nom', 'premierChoix:id,nom,code', 'session:id,code'])
            ->latest('created_at')
            ->paginate(25)
            ->withQueryString();

        return view('concours::admin.candidats.index', [
            'candidats' => $candidats,
            'session'   => $session,
            'centres'   => Centre::query()->where('active', true)->orderBy('nom')->get(['id', 'nom']),
            'statuses'  => [
                Candidat::STATUS_NON    => 'En cours',
                Candidat::STATUS_OUI    => 'Accepté (à payer)',
                Candidat::STATUS_VALID  => 'Payé',
                Candidat::STATUS_REJETE => 'Rejeté',
                Candidat::STATUS_ADMIS  => 'Admis',
            ],
            'filters'   => $request->only(['statut', 'centre_id', 'search']),
        ]);
    }

    public function show(Request $request, Candidat $candidat): View
    {
        if (! $this->checker->can($request->user(), 'view:candidats:*', $candidat)) {
            abort(Response::HTTP_FORBIDDEN);
        }

        $candidat->load([
            'session:id,code,libelle,date_concours',
            'centre',
            'nationalite:id,nom',
            'serieBac:id,nom',
            'premierChoix:id,nom,code',
            'secondChoix:id,nom,code',
            'sectionOrientation:id,nom,code',
            'documents.documentRequis:id,libelle',
            'motifsRejet.decidedBy:id,nom,prenom',
            'payments' => fn ($q) => $q->latest('created_at'),
            'modifications' => fn ($q) => $q->latest('changed_at')->limit(20),
        ]);

        return view('concours::admin.candidats.show', [
            'candidat' => $candidat,
            'canValidate' => $this->checker->can($request->user(), 'validate:candidats:*', $candidat),
        ]);
    }

    /**
     * Single export endpoint reused by every format.
     *
     *   GET /admin/concours/candidats/export.{xlsx|csv|pdf}?statut=valid&centre_id=...
     */
    public function export(Request $request, string $format): Response
    {
        if (! $this->checker->can($request->user(), 'view:candidats:*')) {
            abort(Response::HTTP_FORBIDDEN);
        }

        $session = ConcoursSession::active();
        $query   = $this->buildIndexQuery($request, $session)->orderBy('nom')->orderBy('prenom');

        return ExportBuilder::for($query)
            ->columnsFromModel(Candidat::class)
            ->title('Candidats — ' . ($session?->libelle ?? 'session active'))
            ->meta(array_filter([
                'Session'  => $session?->code,
                'Statut'   => $request->string('statut')->toString() ?: 'tous',
                'Centre'   => $request->string('centre_id')->toString()
                    ? optional(Centre::query()->find($request->string('centre_id')->toString()))->nom
                    : 'tous',
                'Recherche'=> $request->string('search')->toString() ?: null,
            ]))
            ->filename('candidats-' . ($session?->code ?? 'session'))
            ->landscape()
            ->download($format);
    }

    private function buildIndexQuery(Request $request, ?ConcoursSession $session): Builder
    {
        $query = Candidat::query();
        $query = $this->scoped->apply($query, $request->user(), 'view', 'candidats');

        if ($session !== null) {
            $query->where('concours_session_id', $session->id);
        }
        if ($status = $request->string('statut')->toString()) {
            $query->where('statut', $status);
        }
        if ($centreId = $request->string('centre_id')->toString()) {
            $query->where('centre_id', $centreId);
        }
        if ($search = $request->string('search')->toString()) {
            $query->where(function ($q) use ($search): void {
                $q->where('nom', 'ilike', "%{$search}%")
                  ->orWhere('prenom', 'ilike', "%{$search}%")
                  ->orWhere('matricule_public', 'ilike', "%{$search}%")
                  ->orWhere('email', 'ilike', "%{$search}%");
            });
        }

        return $query;
    }
}
