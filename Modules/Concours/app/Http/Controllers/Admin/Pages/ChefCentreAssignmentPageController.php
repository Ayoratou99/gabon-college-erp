<?php

declare(strict_types=1);

namespace Modules\Concours\Http\Controllers\Admin\Pages;

use App\Foundation\Permissions\PermissionChecker;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Modules\Concours\Http\Concerns\GuardsArchivedSession;
use Modules\Concours\Models\Centre;
use Modules\Concours\Models\ChefCentreAssignment;
use Modules\Concours\Models\ConcoursSession;
use Modules\UserManagement\Models\Role;
use Modules\UserManagement\Models\User;
use Symfony\Component\HttpFoundation\Response;

/**
 * Per-session chef-centre assignment workflow.
 *
 *   GET    /admin/concours/chef-centres                → matrix page
 *   POST   /admin/concours/chef-centres/assign         → assign user to centre
 *   POST   /admin/concours/chef-centres/{id}/principal → flip est_principal
 *   DELETE /admin/concours/chef-centres/{id}           → revoke an assignment
 *
 * The list is grouped per centre — each centre card shows its currently
 * assigned chefs (with titulaire/suppléant badges) and a dropdown to add a
 * new one. Eligible users are anyone holding the `chef-centre` role.
 *
 * Cache invalidation: the CandidatCentreResolver caches accessible centres
 * per user × session for 60 s. Every mutation here busts the matching key
 * so the chef sees their new centre immediately next request — without
 * waiting on the TTL.
 *
 * Permission: `manage:chef_centre_assignments:*` (granted to DG / DE /
 * super-admin via RoleSeeder).
 */
final class ChefCentreAssignmentPageController extends Controller
{
    use GuardsArchivedSession;

    public function __construct(
        private readonly PermissionChecker $checker,
        private readonly CacheRepository $cache,
    ) {}

    public function index(Request $request): View
    {
        if (! $this->checker->can($request->user(), 'manage:chef_centre_assignments:*')) {
            abort(Response::HTTP_FORBIDDEN);
        }

        $session = $this->resolveSession($request);
        $centres = Centre::query()
            ->where('active', true)
            ->orderBy('display_order')
            ->orderBy('nom')
            ->get(['id', 'code', 'nom', 'ville']);

        // Group assignments per centre id so the view can hand each card its
        // own row collection without N+1 queries.
        $assignments = $session !== null
            ? ChefCentreAssignment::query()
                ->with(['user:id,nom,prenom,email'])
                ->where('concours_session_id', $session->id)
                ->orderBy('est_principal', 'desc')
                ->get()
                ->groupBy('centre_id')
            : collect();

        // Eligible users = anyone holding the chef-centre role. Soft-deleted
        // users are excluded automatically by the User model's SoftDeletes
        // trait. We intentionally do NOT exclude already-assigned users —
        // a single user can chef multiple centres for the same session
        // (replacement / suppléant scenarios).
        $eligibleUsers = User::query()
            ->whereHas('roles', fn ($q) => $q->where('code', 'chef-centre'))
            ->orderBy('nom')
            ->orderBy('prenom')
            ->get(['id', 'nom', 'prenom', 'email']);

        return view('concours::admin.chef_centres.index', [
            'session'        => $session,
            // sessionEditable: hide every "+ Assigner", "Créer & assigner",
            // "Retirer", "Marquer titulaire" affordance when the working
            // session is archived. The controller methods enforce the same
            // gate so a forged POST also gets a 409.
            'sessionEditable' => $session?->isEditable() ?? false,
            'sessions'       => ConcoursSession::query()->orderByDesc('date_concours')->get(['id', 'code', 'libelle', 'est_active']),
            'centres'        => $centres,
            'assignments'    => $assignments,   // Collection<centre_id, Collection<ChefCentreAssignment>>
            'eligibleUsers'  => $eligibleUsers,
        ]);
    }

    public function assign(Request $request): RedirectResponse
    {
        if (! $this->checker->can($request->user(), 'manage:chef_centre_assignments:*')) {
            abort(Response::HTTP_FORBIDDEN);
        }

        $data = Validator::validate($request->all(), [
            'concours_session_id' => ['required', 'uuid', 'exists:concours_sessions,id'],
            'centre_id'           => ['required', 'uuid', 'exists:centres,id'],
            'user_id'             => ['required', 'uuid', 'exists:users,id'],
            'est_principal'       => ['sometimes', 'boolean'],
        ]);

        $this->assertSessionIdEditable($data['concours_session_id'], "l'affectation chef-centre");

        // Idempotent: if the (session, centre, user) row already exists, do
        // nothing — even if soft-deleted, restore it instead of inserting a
        // duplicate (the cca_unique constraint would 500).
        $existing = ChefCentreAssignment::withTrashed()
            ->where('concours_session_id', $data['concours_session_id'])
            ->where('centre_id', $data['centre_id'])
            ->where('user_id', $data['user_id'])
            ->first();

        if ($existing !== null) {
            $existing->restore();
            $existing->forceFill([
                'est_principal' => (bool) ($data['est_principal'] ?? $existing->est_principal),
                'assigned_at'   => now(),
                'assigned_by_user_id' => $request->user()?->getAuthIdentifier(),
            ])->save();
        } else {
            ChefCentreAssignment::query()->create([
                'concours_session_id' => $data['concours_session_id'],
                'centre_id'           => $data['centre_id'],
                'user_id'             => $data['user_id'],
                'est_principal'       => (bool) ($data['est_principal'] ?? true),
                'assigned_at'         => now(),
                'assigned_by_user_id' => $request->user()?->getAuthIdentifier(),
            ]);
        }

        $this->forgetUserCache($data['user_id'], $data['concours_session_id']);

        return redirect()->route('admin.pages.concours.chef_centres.index', [
            'session' => $data['concours_session_id'],
        ])->with('status', 'Chef de centre assigné.');
    }

    public function togglePrincipal(Request $request, ChefCentreAssignment $assignment): RedirectResponse
    {
        if (! $this->checker->can($request->user(), 'manage:chef_centre_assignments:*')) {
            abort(Response::HTTP_FORBIDDEN);
        }

        $this->assertSessionIdEditable($assignment->concours_session_id, "le rôle titulaire/suppléant");

        $assignment->forceFill(['est_principal' => ! $assignment->est_principal])->save();
        // Toggling principal/suppléant doesn't affect accessible centres,
        // but bust the cache anyway so it stays in sync with any future
        // resolver tweaks.
        $this->forgetUserCache($assignment->user_id, $assignment->concours_session_id);

        return redirect()->route('admin.pages.concours.chef_centres.index', [
            'session' => $assignment->concours_session_id,
        ])->with('status', $assignment->est_principal
            ? 'Marqué comme titulaire.'
            : 'Marqué comme suppléant.');
    }

    /**
     * Create a brand-new user (chef-centre role + temp password + must
     * change at next login) and immediately assign them to a centre.
     *
     *   POST /admin/concours/chef-centres/create-and-assign
     *
     * The temp password is flashed back ONCE so the admin can hand it over
     * by phone / Signal / paper. It is NOT persisted in plaintext anywhere
     * else. The user is forced through the password-change flow on first
     * login via `must_set_password=true`.
     */
    public function createAndAssign(Request $request): RedirectResponse
    {
        if (! $this->checker->can($request->user(), 'manage:chef_centre_assignments:*')) {
            abort(Response::HTTP_FORBIDDEN);
        }

        $data = Validator::validate($request->all(), [
            'concours_session_id' => ['required', 'uuid', 'exists:concours_sessions,id'],
            'centre_id'           => ['required', 'uuid', 'exists:centres,id'],
            'nom'                 => ['required', 'string', 'max:100'],
            'prenom'              => ['required', 'string', 'max:100'],
            'email'               => ['required', 'email:rfc', 'max:191', Rule::unique('users', 'email')->whereNull('deleted_at')],
            'telephone'           => ['nullable', 'string', 'regex:/^[+0-9 .-]{6,30}$/'],
            'est_principal'       => ['sometimes', 'boolean'],
        ], [
            'email.unique' => 'Cet email est déjà utilisé par un autre compte. Choisissez « utilisateur existant » plutôt que d\'en créer un nouveau.',
        ]);

        $this->assertSessionIdEditable($data['concours_session_id'], "un nouveau chef de centre");

        $tempPassword = $this->generateTempPassword();

        DB::transaction(function () use ($data, $tempPassword, $request): void {
            /** @var User $user */
            $user = User::query()->create([
                'nom'               => $data['nom'],
                'prenom'            => $data['prenom'],
                'email'             => $data['email'],
                'telephone'         => $data['telephone'] ?? null,
                'password'          => Hash::make($tempPassword),
                'password_legacy'   => false,
                // Force a password change on first login. With this flag set,
                // the existing first-login wizard catches them after they
                // authenticate with the temp password and walks them through
                // setting a real one + 2FA enrolment.
                'must_set_password' => true,
            ]);

            $chefCentreRole = Role::query()->where('code', 'chef-centre')->firstOrFail();
            $user->roles()->attach($chefCentreRole);

            ChefCentreAssignment::query()->create([
                'concours_session_id' => $data['concours_session_id'],
                'centre_id'           => $data['centre_id'],
                'user_id'             => $user->getKey(),
                'est_principal'       => (bool) ($data['est_principal'] ?? true),
                'assigned_at'         => now(),
                'assigned_by_user_id' => $request->user()?->getAuthIdentifier(),
            ]);

            $this->forgetUserCache($user->getKey(), $data['concours_session_id']);
            $request->session()->put('cuk.last_created_user_id', (string) $user->getKey());
        });

        return redirect()->route('admin.pages.concours.chef_centres.index', [
            'session' => $data['concours_session_id'],
        ])
            ->with('status', "Compte créé et affecté&nbsp;: {$data['prenom']} {$data['nom']}. Communiquez ce mot de passe temporaire&nbsp;:")
            ->with('temp_password', $tempPassword);
    }

    public function destroy(Request $request, ChefCentreAssignment $assignment): RedirectResponse
    {
        if (! $this->checker->can($request->user(), 'manage:chef_centre_assignments:*')) {
            abort(Response::HTTP_FORBIDDEN);
        }

        $sessionId = $assignment->concours_session_id;
        $userId    = $assignment->user_id;

        $this->assertSessionIdEditable($sessionId, "cette affectation");

        $assignment->delete();   // soft-delete (SoftDeletes trait)
        $this->forgetUserCache($userId, $sessionId);

        return redirect()->route('admin.pages.concours.chef_centres.index', [
            'session' => $sessionId,
        ])->with('status', 'Affectation supprimée.');
    }

    private function resolveSession(Request $request): ?ConcoursSession
    {
        $sessionId = $request->string('session')->toString();
        if ($sessionId !== '') {
            $s = ConcoursSession::query()->find($sessionId);
            if ($s !== null) {
                return $s;
            }
        }
        return ConcoursSession::active();
    }

    /**
     * Invalidate the resolver's per-user accessible-centres cache for the
     * given (user, session) pair. The resolver builds the key as
     * `cuk:user:{user_id}:centres:{session_id}`.
     */
    private function forgetUserCache(string $userId, string $sessionId): void
    {
        $this->cache->forget("cuk:user:{$userId}:centres:{$sessionId}");
        $this->cache->forget("cuk:user:{$userId}:regions");
    }

    /**
     * Same alphabet as UserPageController::generateTempPassword — readable
     * over the phone (no 0/O/1/l confusion).
     */
    private function generateTempPassword(int $length = 12): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';
        $max      = strlen($alphabet) - 1;
        $out      = '';
        for ($i = 0; $i < $length; $i++) {
            $out .= $alphabet[random_int(0, $max)];
        }
        return $out;
    }
}
