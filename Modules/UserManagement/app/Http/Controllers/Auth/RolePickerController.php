<?php

declare(strict_types=1);

namespace Modules\UserManagement\Http\Controllers\Auth;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Modules\UserManagement\Models\Role;
use Modules\UserManagement\Models\User;

/**
 * The "which hat are you wearing today?" prompt.
 *
 *   GET  /choisir-role  → picker form (one button per role)
 *   POST /choisir-role  → validates + stores cuk.active_role_id in session
 *   GET  /changer-role  → clears the session key, redirects back to picker
 *
 * Active role is enforced everywhere downstream because User::permissions()
 * filters its query by the session-stored role id. Switching role is
 * a real transition: the cache key flips, the new role's permissions take
 * effect on the very next request.
 *
 * Single-role users never see this — the EnsureActiveRole middleware
 * auto-pins their only role and they go straight to the dashboard.
 */
final class RolePickerController extends Controller
{
    private const SESSION_KEY = 'cuk.active_role_id';

    public function show(Request $request): View|RedirectResponse
    {
        /** @var User|null $user */
        $user = $request->user();
        if ($user === null) {
            return redirect()->route('login');
        }

        $user->loadMissing('roles');

        // Edge cases that shouldn't reach the picker normally — short-circuit.
        if ($user->roles->isEmpty()) {
            // Nobody assigned them a role yet. Sign them out and tell them.
            auth()->logout();
            $request->session()->invalidate();
            return redirect()->route('login')
                ->withErrors(['identifier' => 'Aucun rôle n\'est attribué à votre compte. Contactez un administrateur.']);
        }

        if ($user->roles->count() === 1) {
            // Only one option — pin it and move on.
            $only = $user->roles->first();
            $this->pinRole($request, $user, (string) $only->id);
            return redirect()->intended($this->landingRouteFor($only->code));
        }

        return view('usermanagement::auth.role-picker', [
            'user'  => $user,
            'roles' => $user->roles,
        ]);
    }

    public function select(Request $request): RedirectResponse
    {
        /** @var User|null $user */
        $user = $request->user();
        if ($user === null) {
            return redirect()->route('login');
        }

        $data = Validator::validate($request->all(), [
            'role_id' => ['required', 'uuid'],
        ]);

        // The submitted role must really belong to the user. Defence against
        // a hand-crafted POST trying to elevate to a role they were never
        // granted.
        $user->loadMissing('roles');
        if (! $user->roles->contains('id', $data['role_id'])) {
            return back()->withErrors(['role_id' => 'Ce rôle ne fait pas partie de vos attributions.']);
        }

        $this->pinRole($request, $user, $data['role_id']);

        $pickedRole = $user->roles->firstWhere('id', $data['role_id']);
        return redirect()->intended($this->landingRouteFor((string) $pickedRole?->code));
    }

    /**
     * Per-role landing URL. Étudiants don't have admin access, so we
     * route them to /espace-etudiant instead of the back-office dashboard.
     * Any other role (admin, dg, de, chef-centre, …) lands on /dashboard.
     */
    private function landingRouteFor(string $roleCode): string
    {
        return match ($roleCode) {
            'etudiant' => route('etudiant.space'),
            default    => route('dashboard'),
        };
    }

    /**
     * Drop the active role from the session and bounce to the picker so the
     * user can switch hats mid-session without re-authenticating.
     *
     *   GET /changer-role
     */
    public function switch(Request $request): RedirectResponse
    {
        $request->session()->forget(self::SESSION_KEY);
        return redirect()->route('role.picker');
    }

    private function pinRole(Request $request, User $user, string $roleId): void
    {
        $request->session()->put(self::SESSION_KEY, $roleId);
        // Bust both possible cache keys (the "all" aggregate + any prior
        // per-role cache) so the next permissions() call rebuilds from
        // scratch under the new role.
        $store = Cache::store(config('permissions.cache.store', config('cache.default')));
        $store->forget("cuk:perm:user:{$user->getKey()}:role:all");
        $store->forget("cuk:perm:user:{$user->getKey()}:role:{$roleId}");
    }
}
