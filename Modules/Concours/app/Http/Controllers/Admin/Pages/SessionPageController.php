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
use Modules\AcademicStructure\Models\AnneeAcademique;
use Modules\Concours\Http\Concerns\GuardsArchivedSession;
use Modules\Concours\Models\ConcoursSession;
use Symfony\Component\HttpFoundation\Response;

/**
 * Manage concours sessions:
 *
 *   GET  /admin/concours/sessions                 → list + activate UI
 *   POST /admin/concours/sessions                 → create a new session
 *   PUT  /admin/concours/sessions/{id}            → edit a non-archived session
 *   POST /admin/concours/sessions/switch          → switch the active flag (dashboard)
 *   POST /admin/concours/sessions/{id}/activate   → switch the active flag (list)
 *
 * Only super-admin / dg / de can manage sessions (permission edit:sessions:*).
 */
final class SessionPageController extends Controller
{
    use GuardsArchivedSession;

    public function __construct(
        private readonly PermissionChecker $checker,
    ) {}

    public function index(Request $request): View
    {
        if (! $this->checker->can($request->user(), 'view:sessions:*')) {
            abort(Response::HTTP_FORBIDDEN);
        }

        $sessions = ConcoursSession::query()
            ->with([
                'anneeAcademique:id,code',
                // Only the active publications — feeds lifecycleBadge()'s
                // archived check without an N+1 query across the list.
                'resultPublications' => fn ($q) => $q
                    ->where('active', true)
                    ->select('id', 'concours_session_id', 'active'),
            ])
            // Exclude the QA test candidate from the headline per-session count
            // so the figure always reflects real registrations.
            ->withCount(['candidats as candidats_count' => fn ($q) => $q->where('is_test', false)])
            ->orderByDesc('est_active')
            ->orderByDesc('date_concours')
            ->get();

        return view('concours::admin.sessions.index', [
            'sessions' => $sessions,
            'annees'   => AnneeAcademique::query()->orderByDesc('code')->get(['id', 'code']),
            'canEdit'  => $this->checker->can($request->user(), 'edit:sessions:*'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if (! $this->checker->can($request->user(), 'edit:sessions:*')) {
            abort(Response::HTTP_FORBIDDEN);
        }

        $data = Validator::validate($request->all(), [
            'annee_academique_id'         => ['required', 'uuid', 'exists:annees_academiques,id'],
            'code'                        => ['required', 'string', 'max:60', 'unique:concours_sessions,code'],
            'libelle'                     => ['required', 'string', 'max:191'],
            'date_ouverture_inscriptions' => ['required', 'date'],
            'date_fermeture_inscriptions' => ['required', 'date', 'after_or_equal:date_ouverture_inscriptions'],
            'date_concours'               => ['required', 'date', 'after_or_equal:date_fermeture_inscriptions'],
            'frais_inscription_override'  => ['nullable', 'integer', 'min:0', 'max:10000000'],
            'nombre_choix'                => ['required', 'integer', 'in:1,2'],
            'flyer'                       => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:8192'],
            'activate_now'                => ['sometimes', 'boolean'],
        ]);

        /** @var ConcoursSession $session */
        $session = ConcoursSession::query()->create([
            'annee_academique_id'         => $data['annee_academique_id'],
            'code'                        => $data['code'],
            'libelle'                     => $data['libelle'],
            'date_ouverture_inscriptions' => $data['date_ouverture_inscriptions'],
            'date_fermeture_inscriptions' => $data['date_fermeture_inscriptions'],
            'date_concours'               => $data['date_concours'],
            'frais_inscription_override'  => $data['frais_inscription_override'] ?? null,
            'nombre_choix'                => (int) $data['nombre_choix'],
            'statut'                      => 'ouvert',
            'est_active'                  => false,
        ]);

        $this->persistFlyer($request, $session);

        if ($request->boolean('activate_now')) {
            $session->markAsActive();
        }

        return redirect()->route('admin.pages.concours.sessions.index')
            ->with('status', "Session « {$session->libelle} » créée.");
    }

    /**
     * Store the optional announcement flyer (PDF / image) on the public disk.
     */
    private function persistFlyer(Request $request, ConcoursSession $session): void
    {
        if ($request->hasFile('flyer')) {
            $session->forceFill([
                'flyer_disk' => 'public',
                'flyer_path' => $request->file('flyer')->store('flyers', 'public'),
            ])->save();
        }
    }

    public function activate(Request $request, ConcoursSession $session): RedirectResponse
    {
        if (! $this->checker->can($request->user(), 'edit:sessions:*')) {
            abort(Response::HTTP_FORBIDDEN);
        }

        $session->markAsActive();

        return redirect()->route('admin.pages.concours.sessions.index')
            ->with('status', "Session « {$session->libelle} » activée.");
    }

    /**
     * Switch the back-office active session from the dashboard selector.
     *
     * Same effect as activate() but driven by a <select name="session_id">
     * (so its action URL is fixed — the chosen id travels in the body, not the
     * path). Redirects back to the dashboard, which then resolves to
     * ConcoursSession::active() and shows the freshly selected session.
     *
     *   POST /admin/concours/sessions/switch
     */
    public function switchActive(Request $request): RedirectResponse
    {
        if (! $this->checker->can($request->user(), 'edit:sessions:*')) {
            abort(Response::HTTP_FORBIDDEN);
        }

        $data = Validator::validate($request->all(), [
            'session_id' => ['required', 'uuid', 'exists:concours_sessions,id'],
        ]);

        /** @var ConcoursSession $session */
        $session = ConcoursSession::query()->findOrFail($data['session_id']);
        $session->markAsActive();

        return redirect()->route('dashboard')
            ->with('status', "Session « {$session->libelle} » sélectionnée.");
    }

    /**
     * Edit a NON-archived session. Archived sessions (results published or
     * statut=clos) are read-only — assertSessionEditable() throws 409 with a
     * French reason the UI surfaces. est_active / statut are NOT touched here;
     * they have their own dedicated controls (activate / selection workflow).
     *
     *   PUT /admin/concours/sessions/{session}
     */
    public function update(Request $request, ConcoursSession $session): RedirectResponse
    {
        if (! $this->checker->can($request->user(), 'edit:sessions:*')) {
            abort(Response::HTTP_FORBIDDEN);
        }

        $this->assertSessionEditable($session, 'la session');

        $data = Validator::validate($request->all(), [
            'annee_academique_id'         => ['required', 'uuid', 'exists:annees_academiques,id'],
            'code'                        => ['required', 'string', 'max:60', Rule::unique('concours_sessions', 'code')->ignore($session->getKey())],
            'libelle'                     => ['required', 'string', 'max:191'],
            'date_ouverture_inscriptions' => ['required', 'date'],
            'date_fermeture_inscriptions' => ['required', 'date', 'after_or_equal:date_ouverture_inscriptions'],
            'date_concours'               => ['required', 'date', 'after_or_equal:date_fermeture_inscriptions'],
            'frais_inscription_override'  => ['nullable', 'integer', 'min:0', 'max:10000000'],
            'nombre_choix'                => ['required', 'integer', 'in:1,2'],
            'flyer'                       => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:8192'],
        ]);

        $session->forceFill([
            'annee_academique_id'         => $data['annee_academique_id'],
            'code'                        => $data['code'],
            'libelle'                     => $data['libelle'],
            'date_ouverture_inscriptions' => $data['date_ouverture_inscriptions'],
            'date_fermeture_inscriptions' => $data['date_fermeture_inscriptions'],
            'date_concours'               => $data['date_concours'],
            'frais_inscription_override'  => $data['frais_inscription_override'] ?? null,
            'nombre_choix'                => (int) $data['nombre_choix'],
        ])->save();

        $this->persistFlyer($request, $session);

        return redirect()->route('admin.pages.concours.sessions.index')
            ->with('status', "Session « {$session->libelle} » mise à jour.");
    }
}
