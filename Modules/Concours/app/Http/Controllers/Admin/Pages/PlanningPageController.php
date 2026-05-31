<?php

declare(strict_types=1);

namespace Modules\Concours\Http\Controllers\Admin\Pages;

use App\Foundation\Permissions\PermissionChecker;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Modules\AcademicStructure\Models\Salle;
use Modules\AcademicStructure\Models\Section;
use Modules\Concours\DTOs\SchedulePlanningDto;
use Modules\Concours\Http\Concerns\GuardsArchivedSession;
use Modules\Concours\Models\ConcoursSession;
use Modules\Concours\Models\ConcoursSessionCentre;
use Modules\Concours\Models\Epreuve;
use Modules\Concours\Models\EpreuvePlanning;
use Modules\Concours\Services\PlanningService;
use Symfony\Component\HttpFoundation\Response;

/**
 * Admin scheduling grid for "emploi du temps des épreuves":
 *
 *   GET  /admin/concours/planning            list + per-centre editor
 *   POST /admin/concours/planning            create/update a slot (DTO via service)
 *   POST /admin/concours/planning/inherit    copy every slot from another centre
 *   DELETE /admin/concours/planning/{p}      drop a slot
 *
 * The grid is centre-centric: one centre at a time, with rows = épreuves
 * (cycle- or section-scoped, per the épreuve catalog) and columns = the
 * actual planned date/heure/salle. Scope handling stays in PlanningService
 * + Epreuve.scope_type — this controller only deals with HTTP plumbing.
 */
final class PlanningPageController extends Controller
{
    use GuardsArchivedSession;

    public function __construct(
        private readonly PermissionChecker $checker,
        private readonly PlanningService $planningService,
    ) {}

    public function index(Request $request): View
    {
        $this->ensure($request, 'view:planning:*', 'view:planning:own_center');

        $session = ConcoursSession::active();
        if ($session === null) {
            return view('concours::admin.planning.index', [
                'session' => null,
                'centres' => collect(),
                'selectedCentre' => null,
                'rows'    => collect(),
                'salles'  => collect(),
                'otherCentres' => collect(),
                'canEdit' => false,
            ]);
        }

        $centres = $session->centres()
            ->wherePivot('active', true)
            ->orderBy('nom')
            ->get(['centres.id', 'centres.nom']);

        $selectedCentreId = $request->query('centre')
            ?? (string) $centres->first()?->id;

        $selectedCentre = $centres->firstWhere('id', $selectedCentreId);
        $sessionCentre  = $selectedCentre?->pivot;

        // Build the rows: every épreuve of the session, with its planning (if
        // any) at the selected centre. Cycle/section scopes are surfaced
        // verbatim — the planner needs to see both.
        $epreuves = Epreuve::query()
            ->where('concours_session_id', $session->id)
            ->where('active', true)
            ->orderBy('ordre')->orderBy('code')
            ->with(['typeEpreuve:id,libelle'])
            ->get();

        $plannings = $sessionCentre !== null
            ? EpreuvePlanning::query()
                ->where('concours_session_centre_id', $sessionCentre->id)
                ->get()->keyBy('epreuve_id')
            : collect();

        $scopeLabels = $this->scopeLabels($epreuves);

        $rows = $epreuves->map(fn (Epreuve $e) => [
            'epreuve'  => $e,
            'planning' => $plannings->get($e->id),
            'scope'    => $scopeLabels[$e->id] ?? '—',
        ]);

        $sessionEditable = $session->isEditable();

        return view('concours::admin.planning.index', [
            'session'         => $session,
            'sessionEditable' => $sessionEditable,
            'centres'         => $centres,
            'selectedCentre'  => $selectedCentre,
            'rows'            => $rows,
            'salles'          => Salle::query()->where('active', true)->orderBy('nom')->get(['id', 'nom', 'batiment', 'capacite']),
            'otherCentres'    => $centres->filter(fn ($c) => $c->id !== $selectedCentreId)->values(),
            // canEdit gates every "Planifier", "Importer", "Supprimer" button —
            // we AND the permission with the lifecycle gate so archived
            // sessions render read-only even for a DG / DE.
            'canEdit'         => $this->checker->can($request->user(), 'manage:planning:*')
                              && $sessionEditable,
        ]);
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $this->ensure($request, 'manage:planning:*');
        $this->assertSessionEditable(ConcoursSession::active(), 'le planning');

        $data = Validator::validate($request->all(), [
            'epreuve_id'                 => ['required', 'uuid', 'exists:epreuves,id'],
            'concours_session_centre_id' => ['required', 'uuid', 'exists:concours_session_centres,id'],
            'salle_id'                   => ['nullable', 'uuid', 'exists:salles,id'],
            'date_epreuve'               => ['required', 'date'],
            'heure_debut'                => ['required', 'date_format:H:i'],
            'heure_fin'                  => ['required', 'date_format:H:i', 'after:heure_debut'],
            'consigne'                   => ['nullable', 'string', 'max:1000'],
        ]);

        $result = $this->planningService->schedule(new SchedulePlanningDto(
            epreuveId:                $data['epreuve_id'],
            concoursSessionCentreId:  $data['concours_session_centre_id'],
            salleId:                  $data['salle_id'] ?? null,
            dateEpreuve:              $data['date_epreuve'],
            heureDebut:               $data['heure_debut'],
            heureFin:                 $data['heure_fin'],
            consigne:                 $data['consigne'] ?? null,
        ));

        return back()->with('status', $result['conflicts']->isEmpty()
            ? 'Créneau enregistré.'
            : 'Créneau enregistré — attention, conflit de salle détecté.');
    }

    public function destroy(Request $request, EpreuvePlanning $planning): RedirectResponse
    {
        $this->ensure($request, 'manage:planning:*');
        $planning->loadMissing('epreuve.session');
        $this->assertSessionEditable($planning->epreuve?->session, 'ce créneau');
        $planning->delete();
        return back()->with('status', 'Créneau supprimé.');
    }

    /**
     * Bulk copy every planning from `source_centre_id` (a ConcoursSessionCentre
     * pivot id) to `target_centre_id`. Existing slots at the target for the
     * same épreuve are overwritten with the source's date / heures (salle is
     * cleared since the source's salle belongs to another centre).
     */
    public function inherit(Request $request): RedirectResponse
    {
        $this->ensure($request, 'manage:planning:*');
        $this->assertSessionEditable(ConcoursSession::active(), "l'importation du planning");

        $data = Validator::validate($request->all(), [
            'source_session_centre_id' => ['required', 'uuid', 'exists:concours_session_centres,id'],
            'target_session_centre_id' => ['required', 'uuid', 'exists:concours_session_centres,id', 'different:source_session_centre_id'],
        ]);

        $source = EpreuvePlanning::query()
            ->where('concours_session_centre_id', $data['source_session_centre_id'])
            ->get();

        $copied = 0;
        DB::transaction(function () use ($source, $data, &$copied): void {
            foreach ($source as $row) {
                EpreuvePlanning::query()->updateOrCreate(
                    [
                        'epreuve_id'                 => $row->epreuve_id,
                        'concours_session_centre_id' => $data['target_session_centre_id'],
                    ],
                    [
                        'salle_id'     => null,   // belongs to source centre — cleared
                        'date_epreuve' => $row->date_epreuve,
                        'heure_debut'  => $row->heure_debut,
                        'heure_fin'    => $row->heure_fin,
                        'consigne'     => $row->consigne,
                    ],
                );
                $copied++;
            }
        });

        $targetId = ConcoursSessionCentre::query()->find($data['target_session_centre_id'])?->centre_id;
        return redirect()
            ->route('admin.pages.concours.planning.index', ['centre' => $targetId])
            ->with('status', "{$copied} créneaux importés depuis l'autre centre. Pensez à renseigner les salles locales.");
    }

    /**
     * Pretty-name the scope of each épreuve so the admin sees at a glance
     * whether a row applies to a whole cycle (broad) or a single section.
     *
     * @param  \Illuminate\Database\Eloquent\Collection<int, Epreuve>  $epreuves
     * @return array<string, string>  epreuve_id → label
     */
    private function scopeLabels($epreuves): array
    {
        $sectionIds = $epreuves->where('scope_type', 'section')->pluck('scope_id')->filter()->all();
        $cycleIds   = $epreuves->where('scope_type', 'cycle')->pluck('scope_id')->filter()->all();

        $sectionNames = Section::query()->whereIn('id', $sectionIds)->pluck('nom', 'id');
        $cycleNames   = \Modules\AcademicStructure\Models\Cycle::query()->whereIn('id', $cycleIds)->pluck('nom', 'id');

        $out = [];
        foreach ($epreuves as $e) {
            $out[$e->id] = $e->scope_type === 'cycle'
                ? 'Cycle : ' . ($cycleNames[$e->scope_id] ?? '?')
                : 'Section : ' . ($sectionNames[$e->scope_id] ?? '?');
        }
        return $out;
    }

    private function ensure(Request $request, string ...$permissions): void
    {
        foreach ($permissions as $p) {
            if ($this->checker->can($request->user(), $p)) { return; }
        }
        abort(Response::HTTP_FORBIDDEN);
    }
}
