<?php

declare(strict_types=1);

namespace Modules\Concours\Http\Controllers\Admin\Pages;

use App\Foundation\Identity\Contracts\UserScopeResolver;
use App\Foundation\Permissions\PermissionChecker;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Modules\AcademicStructure\Models\Section;
use Modules\Concours\Http\Concerns\GuardsArchivedSession;
use Modules\Concours\Models\Centre;
use Modules\Concours\Models\ConcoursSession;
use Modules\Concours\Models\ConcoursSessionCentre;
use Modules\Concours\Models\Epreuve;
use Modules\Concours\Models\EpreuvePlanning;
use Symfony\Component\HttpFoundation\Response;

/**
 * Drag-and-drop "emploi du temps des épreuves" board, one centre at a time.
 *
 *   GET    /admin/concours/planning              the board
 *   POST   /admin/concours/planning              add/upsert an ÉPREUVE slot
 *   POST   /admin/concours/planning/break        add a FREE line (pause déjeuner…)
 *   PUT    /admin/concours/planning/{p}          edit a slot's date/heures/label
 *   POST   /admin/concours/planning/reorder      persist drag order (JSON)
 *   POST   /admin/concours/planning/inherit      copy every slot from another centre
 *   DELETE /admin/concours/planning/{p}          drop a slot
 *
 * No salle/room is recorded: with many centres assigning rooms per centre is
 * impractical, so the timetable is room-less. Slots (épreuves + free lines) are
 * ordered by a manual `ordre` the board drag-and-drop persists — that order is
 * exactly what the candidate sees on their emploi du temps.
 */
final class PlanningPageController extends Controller
{
    use GuardsArchivedSession;

    public function __construct(
        private readonly PermissionChecker $checker,
        private readonly UserScopeResolver $scope,
    ) {}

    public function index(Request $request): View
    {
        $this->ensure($request, 'view:planning:*', 'view:planning:own_center');

        $session = ConcoursSession::active();
        if ($session === null) {
            return view('concours::admin.planning.index', [
                'session' => null, 'centres' => collect(), 'selectedCentre' => null,
                'sessionCentreId' => null, 'slots' => collect(), 'unplanned' => collect(),
                'progress' => [], 'otherCentres' => collect(), 'canEdit' => false,
                'planningNote' => null,
            ]);
        }

        // A session uses every ACTIVE centre by default (the same set inscription
        // lists). Lazily attach them to the session pivot the first time an editor
        // opens the board, so the planning board never dead-ends on "no centre
        // attached" while inscription happily shows centres.
        if ($session->isEditable() && $this->checker->can($request->user(), 'manage:planning:*')) {
            $this->ensureSessionCentres($session);
        }

        $centresQuery = $session->centres()
            ->wherePivot('active', true)
            ->orderBy('nom');
        // A chef-centre (no global planning view) only sees / edits HIS centres.
        if (! $this->checker->can($request->user(), 'view:planning:*')) {
            $centresQuery->whereIn('centres.id', $this->scope->accessibleCentreIds($request->user()));
        }
        $centres = $centresQuery->get(['centres.id', 'centres.nom']);

        $selectedCentreId = $request->query('centre') ?? (string) $centres->first()?->id;
        $selectedCentre   = $centres->firstWhere('id', $selectedCentreId);
        $sessionCentre    = $selectedCentre?->pivot;

        // Every active épreuve of the session + its sections (for the "add" picker
        // and the per-section progress tracker).
        $epreuves = Epreuve::query()
            ->where('concours_session_id', $session->id)
            ->where('active', true)
            ->with(['typeEpreuve:id,libelle', 'sections:id,code,nom'])
            ->orderBy('ordre')->orderBy('code')
            ->get();

        $slots             = collect();
        $plannedEpreuveIds = [];
        if ($sessionCentre !== null) {
            $slots = EpreuvePlanning::query()
                ->where('concours_session_centre_id', $sessionCentre->id)
                ->with(['epreuve' => fn ($q) => $q->with(['typeEpreuve:id,libelle', 'sections:id,code'])])
                ->orderBy('ordre')->orderBy('date_epreuve')->orderBy('heure_debut')
                ->get();
            $plannedEpreuveIds = $slots->whereNotNull('epreuve_id')->pluck('epreuve_id')->all();
        }

        $sessionEditable = $session->isEditable();

        return view('concours::admin.planning.index', [
            'session'         => $session,
            'sessionEditable' => $sessionEditable,
            'centres'         => $centres,
            'selectedCentre'  => $selectedCentre,
            'sessionCentreId' => $sessionCentre?->id,
            'slots'           => $slots,
            // Épreuves not yet placed at this centre — feed the "Ajouter une épreuve" select.
            'unplanned'       => $epreuves->whereNotIn('id', $plannedEpreuveIds)->values(),
            'progress'        => $this->sectionProgress($epreuves, $plannedEpreuveIds),
            'otherCentres'    => $centres->filter(fn ($c) => $c->id !== $selectedCentreId)->values(),
            'planningNote'    => $session->planning_note,
            'canEdit'         => ($this->checker->can($request->user(), 'manage:planning:*')
                                  || $this->checker->can($request->user(), 'manage:planning:own_center'))
                                  && $sessionEditable,
        ]);
    }

    /**
     * Session-level note shown at the BOTTOM of every candidat's emploi du temps
     * PDF. Edited from the planning board (one note for the whole concours, so
     * it doesn't have to be retyped per centre).
     */
    public function saveNote(Request $request): RedirectResponse
    {
        $this->ensure($request, 'manage:planning:*');
        $session = ConcoursSession::active();
        $this->assertSessionEditable($session, 'le planning');

        $data = Validator::validate($request->all(), [
            'planning_note' => ['nullable', 'string', 'max:5000'],
        ]);

        $session?->forceFill(['planning_note' => $data['planning_note'] ?? null])->save();

        return back()->with('status', "Note de l'emploi du temps enregistrée.");
    }

    public function store(Request $request): RedirectResponse
    {
        $this->assertSessionEditable(ConcoursSession::active(), 'le planning');

        $data = Validator::validate($request->all(), [
            'epreuve_id'                 => ['required', 'uuid', 'exists:epreuves,id'],
            'concours_session_centre_id' => ['required', 'uuid', 'exists:concours_session_centres,id'],
            'date_epreuve'               => ['required', 'date'],
            'heure_debut'                => ['required', 'date_format:H:i'],
            'heure_fin'                  => ['required', 'date_format:H:i', 'after:heure_debut'],
            'consigne'                   => ['nullable', 'string', 'max:1000'],
            'classe'                     => ['nullable', 'string', 'max:191'],
        ]);
        $this->assertCanManageCentre($request, $data['concours_session_centre_id']);

        // One slot per (épreuve × centre): upsert. Only stamp `ordre` on first
        // creation so editing a slot doesn't bump it to the end of the board.
        $planning = EpreuvePlanning::query()->firstOrNew([
            'epreuve_id'                 => $data['epreuve_id'],
            'concours_session_centre_id' => $data['concours_session_centre_id'],
        ]);
        $planning->fill([
            'kind'         => EpreuvePlanning::KIND_EPREUVE,
            'date_epreuve' => $data['date_epreuve'],
            'heure_debut'  => $this->time($data['heure_debut']),
            'heure_fin'    => $this->time($data['heure_fin']),
            'consigne'     => $data['consigne'] ?? null,
            'classe'       => $data['classe'] ?? null,
        ]);
        if (! $planning->exists) {
            $planning->ordre = $this->nextOrdre($data['concours_session_centre_id']);
        }
        $planning->save();

        return back()->with('status', 'Créneau enregistré.');
    }

    public function storeBreak(Request $request): RedirectResponse
    {
        $this->assertSessionEditable(ConcoursSession::active(), 'le planning');

        $data = Validator::validate($request->all(), [
            'concours_session_centre_id' => ['required', 'uuid', 'exists:concours_session_centres,id'],
            'libelle_libre'              => ['required', 'string', 'max:191'],
            'kind'                       => ['nullable', 'in:pause,autre'],
            'date_epreuve'               => ['required', 'date'],
            'heure_debut'                => ['required', 'date_format:H:i'],
            'heure_fin'                  => ['required', 'date_format:H:i', 'after:heure_debut'],
        ]);
        $this->assertCanManageCentre($request, $data['concours_session_centre_id']);

        EpreuvePlanning::query()->create([
            'epreuve_id'                 => null,
            'kind'                       => $data['kind'] ?? EpreuvePlanning::KIND_PAUSE,
            'concours_session_centre_id' => $data['concours_session_centre_id'],
            'libelle_libre'              => $data['libelle_libre'],
            'date_epreuve'               => $data['date_epreuve'],
            'heure_debut'                => $this->time($data['heure_debut']),
            'heure_fin'                  => $this->time($data['heure_fin']),
            'ordre'                      => $this->nextOrdre($data['concours_session_centre_id']),
        ]);

        return back()->with('status', "Ligne ajoutée à l'emploi du temps.");
    }

    public function update(Request $request, EpreuvePlanning $planning): RedirectResponse
    {
        $this->assertCanManageCentre($request, $planning->concours_session_centre_id);
        $planning->loadMissing('epreuve.session');
        $this->assertSessionEditable($planning->epreuve?->session ?? ConcoursSession::active(), 'ce créneau');

        $rules = [
            'date_epreuve' => ['required', 'date'],
            'heure_debut'  => ['required', 'date_format:H:i'],
            'heure_fin'    => ['required', 'date_format:H:i', 'after:heure_debut'],
            'consigne'     => ['nullable', 'string', 'max:1000'],
            'classe'       => ['nullable', 'string', 'max:191'],
        ];
        if ($planning->isBreak()) {
            $rules['libelle_libre'] = ['required', 'string', 'max:191'];
        }
        $data = Validator::validate($request->all(), $rules);

        $planning->fill([
            'date_epreuve' => $data['date_epreuve'],
            'heure_debut'  => $this->time($data['heure_debut']),
            'heure_fin'    => $this->time($data['heure_fin']),
            'consigne'     => $data['consigne'] ?? null,
            'classe'       => $data['classe'] ?? null,
        ]);
        if ($planning->isBreak()) {
            $planning->libelle_libre = $data['libelle_libre'];
        }
        $planning->save();

        return back()->with('status', 'Créneau mis à jour.');
    }

    /**
     * Persist the drag-and-drop order. Body: { order: [planningId, …] } — the
     * array index becomes the row's `ordre`.
     */
    public function reorder(Request $request): JsonResponse
    {
        $this->assertSessionEditable(ConcoursSession::active(), 'le planning');

        $data = Validator::validate($request->all(), [
            'order'   => ['required', 'array'],
            'order.*' => ['uuid', 'exists:epreuve_plannings,id'],
        ]);

        // Every reordered slot must sit in a centre the user is allowed to manage.
        if (! $this->checker->can($request->user(), 'manage:planning:*')) {
            $this->ensure($request, 'manage:planning:own_center');
            $accessible = $this->scope->accessibleCentreIds($request->user());
            $centreIds = ConcoursSessionCentre::query()
                ->whereIn('id', EpreuvePlanning::query()->whereIn('id', $data['order'])->distinct()->pluck('concours_session_centre_id'))
                ->pluck('centre_id');
            foreach ($centreIds as $cid) {
                if (! in_array((string) $cid, $accessible, true)) {
                    abort(Response::HTTP_FORBIDDEN);
                }
            }
        }

        DB::transaction(function () use ($data): void {
            foreach ($data['order'] as $i => $id) {
                EpreuvePlanning::query()->whereKey($id)->update(['ordre' => $i]);
            }
        });

        return response()->json(['ok' => true]);
    }

    public function destroy(Request $request, EpreuvePlanning $planning): RedirectResponse
    {
        $this->assertCanManageCentre($request, $planning->concours_session_centre_id);
        $planning->loadMissing('epreuve.session');
        $this->assertSessionEditable($planning->epreuve?->session ?? ConcoursSession::active(), 'ce créneau');
        $planning->delete();

        return back()->with('status', 'Créneau supprimé.');
    }

    /**
     * Copy every slot (épreuves + free lines) from another centre to this one,
     * preserving dates / heures / order. Existing épreuve slots are overwritten.
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
            ->orderBy('ordre')
            ->get();

        $copied = 0;
        DB::transaction(function () use ($source, $data, &$copied): void {
            // Wipe the target's free lines first (épreuve slots are upserted).
            EpreuvePlanning::query()
                ->where('concours_session_centre_id', $data['target_session_centre_id'])
                ->whereNull('epreuve_id')
                ->delete();

            foreach ($source as $row) {
                if ($row->epreuve_id === null) {
                    EpreuvePlanning::query()->create([
                        'epreuve_id'                 => null,
                        'kind'                       => $row->kind,
                        'concours_session_centre_id' => $data['target_session_centre_id'],
                        'libelle_libre'              => $row->libelle_libre,
                        'date_epreuve'               => $row->date_epreuve,
                        'heure_debut'                => $row->heure_debut,
                        'heure_fin'                  => $row->heure_fin,
                        'ordre'                      => $row->ordre,
                    ]);
                } else {
                    EpreuvePlanning::query()->updateOrCreate(
                        [
                            'epreuve_id'                 => $row->epreuve_id,
                            'concours_session_centre_id' => $data['target_session_centre_id'],
                        ],
                        [
                            'kind'         => EpreuvePlanning::KIND_EPREUVE,
                            'date_epreuve' => $row->date_epreuve,
                            'heure_debut'  => $row->heure_debut,
                            'heure_fin'    => $row->heure_fin,
                            'consigne'     => $row->consigne,
                            'ordre'        => $row->ordre,
                        ],
                    );
                }
                $copied++;
            }
        });

        $targetCentreId = \Modules\Concours\Models\ConcoursSessionCentre::query()
            ->find($data['target_session_centre_id'])?->centre_id;

        return redirect()
            ->route('admin.pages.concours.planning.index', ['centre' => $targetCentreId])
            ->with('status', "{$copied} créneaux importés depuis l'autre centre.");
    }

    // ----------------------------------------------------- helpers

    /**
     * Per-section completion tracker: how many of a section's épreuves are
     * placed at this centre. Drives the "emploi du temps incomplet" indicator.
     *
     * @param  \Illuminate\Support\Collection<int, Epreuve>  $epreuves
     * @param  list<string>  $plannedEpreuveIds
     * @return list<array{code:string, nom:string, total:int, planned:int, complete:bool}>
     */
    private function sectionProgress($epreuves, array $plannedEpreuveIds): array
    {
        $planned  = array_flip($plannedEpreuveIds);
        $sections = Section::query()
            ->where('active', true)->where('ouvert_au_concours', true)
            ->orderBy('display_order')->orderBy('nom')
            ->get(['id', 'code', 'nom']);

        $out = [];
        foreach ($sections as $sec) {
            $forSection = $epreuves->filter(fn (Epreuve $e) => $e->sections->contains('id', $sec->id));
            $total = $forSection->count();
            if ($total === 0) {
                continue; // no épreuve targets this section — nothing to plan here
            }
            $done = $forSection->filter(fn (Epreuve $e) => isset($planned[$e->id]))->count();
            $out[] = [
                'code'     => (string) $sec->code,
                'nom'      => (string) $sec->nom,
                'total'    => $total,
                'planned'  => $done,
                'complete' => $done === $total,
            ];
        }

        return $out;
    }

    private function nextOrdre(string $sessionCentreId): int
    {
        return (int) EpreuvePlanning::query()
            ->where('concours_session_centre_id', $sessionCentreId)
            ->max('ordre') + 1;
    }

    private function time(string $hm): string
    {
        return strlen($hm) === 5 ? $hm . ':00' : $hm; // "08:30" → "08:30:00"
    }

    /**
     * Attach every active centre to the session (idempotent) when it has none
     * yet — a session defaults to the full set of active centres.
     */
    private function ensureSessionCentres(ConcoursSession $session): void
    {
        if ($session->centres()->wherePivot('active', true)->exists()) {
            return;
        }

        foreach (Centre::query()->where('active', true)->pluck('id') as $centreId) {
            ConcoursSessionCentre::query()->firstOrCreate(
                ['concours_session_id' => $session->id, 'centre_id' => $centreId],
                ['active' => true],
            );
        }
    }

    /**
     * Authorize a planning mutation on a session-centre: manage:planning:* passes
     * for any centre; manage:planning:own_center only for the chef's own centres.
     */
    private function assertCanManageCentre(Request $request, ?string $concoursSessionCentreId): void
    {
        $user = $request->user();
        if ($this->checker->can($user, 'manage:planning:*')) {
            return;
        }
        if ($this->checker->can($user, 'manage:planning:own_center') && $concoursSessionCentreId !== null) {
            $centreId = (string) ConcoursSessionCentre::query()->whereKey($concoursSessionCentreId)->value('centre_id');
            if ($centreId !== '' && in_array($centreId, $this->scope->accessibleCentreIds($user), true)) {
                return;
            }
        }
        abort(Response::HTTP_FORBIDDEN);
    }

    private function ensure(Request $request, string ...$permissions): void
    {
        foreach ($permissions as $p) {
            if ($this->checker->can($request->user(), $p)) {
                return;
            }
        }
        abort(Response::HTTP_FORBIDDEN);
    }
}
