<?php

declare(strict_types=1);

namespace Modules\Concours\Http\Controllers\Admin\Pages;

use App\Foundation\Permissions\PermissionChecker;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Modules\AcademicStructure\Models\AnneeAcademique;
use Modules\Concours\Models\ConcoursSession;
use Symfony\Component\HttpFoundation\Response;

/**
 * Manage concours sessions:
 *
 *   GET  /admin/concours/sessions                 → list + activate UI
 *   POST /admin/concours/sessions                 → create a new session
 *   POST /admin/concours/sessions/{id}/activate   → switch the active flag
 *
 * Only super-admin / dg / de can manage sessions (permission edit:sessions:*).
 */
final class SessionPageController extends Controller
{
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
            ->withCount('candidats')
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
            'statut'                      => 'ouvert',
            'est_active'                  => false,
        ]);

        if ($request->boolean('activate_now')) {
            $session->markAsActive();
        }

        return redirect()->route('admin.pages.concours.sessions.index')
            ->with('status', "Session « {$session->libelle} » créée.");
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
}
