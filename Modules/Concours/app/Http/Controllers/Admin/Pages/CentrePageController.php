<?php

declare(strict_types=1);

namespace Modules\Concours\Http\Controllers\Admin\Pages;

use App\Foundation\Permissions\PermissionChecker;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Modules\Concours\Models\Centre;
use Modules\Concours\Models\ChefCentreAssignment;
use Modules\Concours\Models\ConcoursSession;
use Modules\Referentiels\Models\Province;
use Symfony\Component\HttpFoundation\Response;

/**
 * Manage exam centres:
 *
 *   GET  /admin/concours/centres                  → list + inline create form
 *   POST /admin/concours/centres                  → create
 *   POST /admin/concours/centres/{centre}         → update (uses `_method=PATCH`)
 *   POST /admin/concours/centres/{centre}/toggle  → flip active on/off
 *
 * Edit happens inline in the list — each row is its own collapsible card.
 * Soft delete is implemented as "active=false" + the SoftDeletes trait on the
 * model (real delete is reserved for super-admin via the JSON API to avoid
 * orphan candidats; toggling active is the everyday UX).
 *
 * Permissions:
 *   - view:centres:*    → DG / DE / super-admin (list + read)
 *   - edit:centres:*    → DG / DE / super-admin (create / update / toggle)
 *
 * Chef-centres see their own centre through other surfaces (candidats list,
 * planning, etc.) — they don't need access to this CRUD.
 */
final class CentrePageController extends Controller
{
    public function __construct(
        private readonly PermissionChecker $checker,
    ) {}

    public function index(Request $request): View
    {
        // Gate on `edit:centres:*` rather than `view:centres:*`. Reason:
        // chef-centre holds `view:centres:own_center`, and the OwnCenterResolver
        // returns true when no target row is passed (so that index queries can
        // filter via applyToQuery). That means a generic `view:centres:*`
        // check passes for a chef-centre with at least one assignment — which
        // would leak the full centres CRUD into their view. Since this page
        // is a management surface (create + edit + toggle), requiring
        // `edit:centres:*` keeps it scoped to DG / DE / super-admin without
        // changing the foundation's semantics.
        if (! $this->checker->can($request->user(), 'edit:centres:*')) {
            abort(Response::HTTP_FORBIDDEN);
        }

        $activeSession = ConcoursSession::active();

        // Load each centre with: # of chef-centres assigned (for the active
        // session) and # of candidats (active session). Keeps the list
        // self-explanatory — admins shouldn't have to drill in to know "is
        // this centre staffed yet?".
        $centres = Centre::query()
            ->with('province:id,nom')
            ->withCount([
                'sessions as sessions_count',
            ])
            ->orderBy('display_order')
            ->orderBy('nom')
            ->get();

        // Per-centre stats for the active session (chef count + candidats).
        // Done as a separate aggregated query to keep the eager-loads clean.
        $chefCounts     = [];
        $candidatCounts = [];
        if ($activeSession !== null) {
            $chefCounts = ChefCentreAssignment::query()
                ->where('concours_session_id', $activeSession->id)
                ->selectRaw('centre_id, count(*) as n')
                ->groupBy('centre_id')
                ->pluck('n', 'centre_id')
                ->all();
            $candidatCounts = \Modules\Concours\Models\Candidat::query()
                ->where('concours_session_id', $activeSession->id)
                ->selectRaw('centre_id, count(*) as n')
                ->groupBy('centre_id')
                ->pluck('n', 'centre_id')
                ->all();
        }

        return view('concours::admin.centres.index', [
            'centres'        => $centres,
            'provinces'      => Province::query()->where('active', true)->orderBy('nom')->get(['id', 'nom']),
            'activeSession'  => $activeSession,
            'chefCounts'     => $chefCounts,
            'candidatCounts' => $candidatCounts,
            'canEdit'        => $this->checker->can($request->user(), 'edit:centres:*'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if (! $this->checker->can($request->user(), 'edit:centres:*')) {
            abort(Response::HTTP_FORBIDDEN);
        }

        $data = Validator::validate($request->all(), [
            'code'                => ['required', 'string', 'max:30', 'unique:centres,code'],
            'nom'                 => ['required', 'string', 'max:100'],
            'ville'               => ['nullable', 'string', 'max:100'],
            'province_id'         => ['nullable', 'uuid', 'exists:provinces,id'],
            'adresse'             => ['nullable', 'string', 'max:500'],
            'capacite_par_defaut' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'display_order'       => ['nullable', 'integer', 'min:0', 'max:999'],
            'active'              => ['sometimes', 'boolean'],
        ]);

        Centre::query()->create([
            'code'                => $data['code'],
            'nom'                 => $data['nom'],
            'ville'               => $data['ville'] ?? null,
            'province_id'         => $data['province_id'] ?? null,
            'adresse'             => $data['adresse'] ?? null,
            'capacite_par_defaut' => $data['capacite_par_defaut'] ?? 200,
            'display_order'       => $data['display_order'] ?? 0,
            'active'              => (bool) ($data['active'] ?? true),
        ]);

        return redirect()->route('admin.pages.concours.centres.index')
            ->with('status', "Centre « {$data['nom']} » créé.");
    }

    public function update(Request $request, Centre $centre): RedirectResponse
    {
        if (! $this->checker->can($request->user(), 'edit:centres:*')) {
            abort(Response::HTTP_FORBIDDEN);
        }

        $data = Validator::validate($request->all(), [
            'code'                => ['required', 'string', 'max:30', Rule::unique('centres', 'code')->ignore($centre->id)],
            'nom'                 => ['required', 'string', 'max:100'],
            'ville'               => ['nullable', 'string', 'max:100'],
            'province_id'         => ['nullable', 'uuid', 'exists:provinces,id'],
            'adresse'             => ['nullable', 'string', 'max:500'],
            'capacite_par_defaut' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'display_order'       => ['nullable', 'integer', 'min:0', 'max:999'],
            'active'              => ['sometimes', 'boolean'],
        ]);

        $centre->forceFill([
            'code'                => $data['code'],
            'nom'                 => $data['nom'],
            'ville'               => $data['ville'] ?? null,
            'province_id'         => $data['province_id'] ?? null,
            'adresse'             => $data['adresse'] ?? null,
            'capacite_par_defaut' => $data['capacite_par_defaut'] ?? $centre->capacite_par_defaut,
            'display_order'       => $data['display_order'] ?? $centre->display_order,
            'active'              => (bool) ($data['active'] ?? $centre->active),
        ])->save();

        return redirect()->route('admin.pages.concours.centres.index')
            ->with('status', "Centre « {$centre->nom} » mis à jour.");
    }

    public function toggleActive(Request $request, Centre $centre): RedirectResponse
    {
        if (! $this->checker->can($request->user(), 'edit:centres:*')) {
            abort(Response::HTTP_FORBIDDEN);
        }

        $centre->forceFill(['active' => ! $centre->active])->save();

        return redirect()->route('admin.pages.concours.centres.index')
            ->with('status', $centre->active
                ? "Centre « {$centre->nom} » réactivé."
                : "Centre « {$centre->nom} » désactivé.");
    }
}
