<?php

declare(strict_types=1);

namespace Modules\UserManagement\Http\Controllers\Admin\Pages;

use App\Foundation\Http\DataTables\DataTablesQuery;
use App\Foundation\Permissions\PermissionChecker;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Modules\UserManagement\Models\Role;
use Modules\UserManagement\Models\User;
use Symfony\Component\HttpFoundation\Response;

/**
 * Admin pages for user management:
 *
 *   GET  /admin/users                   list (HTML + DataTable AJAX)
 *   POST /admin/users/data              DataTables JSON feed
 *   GET  /admin/users/{user}            detail page (roles, 2FA, login attempts)
 *   POST /admin/users/{user}/roles      sync the user's roles
 *   POST /admin/users/{user}/reset-2fa  clear google2fa_secret + confirmed_at
 *   POST /admin/users/{user}/reset-password
 *                                       force the user back through first-login wizard
 */
final class UserPageController extends Controller
{
    public function __construct(
        private readonly PermissionChecker $checker,
    ) {}

    public function index(Request $request): View
    {
        $this->ensure($request, 'view:users:*');

        return view('usermanagement::admin.users.index', [
            'canEdit' => $this->checker->can($request->user(), 'edit:users:*'),
            'roles'   => Role::query()->orderBy('name')->get(['id', 'code', 'name']),
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $this->ensure($request, 'view:users:*');

        $query = User::query()
            ->with(['roles:id,code,name'])
            ->select(['id', 'nom', 'prenom', 'email', 'telephone',
                      'password_legacy', 'must_set_password',
                      'google2fa_secret', 'google2fa_confirmed_at',
                      'last_login_at']);

        return DataTablesQuery::for($query)
            ->searchable(['nom', 'prenom', 'email', 'telephone'])
            ->orderable([
                'nom'           => 'nom',
                'email'         => 'email',
                'telephone'     => 'telephone',
                'last_login_at' => 'last_login_at',
            ])
            ->filterUsing(function (Builder $q, array $filters): void {
                if (! empty($filters['role'])) {
                    $q->whereHas('roles', fn ($r) => $r->where('roles.code', $filters['role']));
                }
                if (! empty($filters['status'])) {
                    match ($filters['status']) {
                        'legacy'    => $q->where('password_legacy', true),
                        'must_set'  => $q->where('must_set_password', true),
                        '2fa_off'   => $q->whereNull('google2fa_confirmed_at'),
                        default     => null,
                    };
                }
            })
            ->transform(function (User $u): array {
                $roles = $u->roles->pluck('name')->implode(', ');
                $twofa = $u->google2fa_confirmed_at
                    ? '<span class="badge bg-success-subtle text-success-emphasis"><i class="fas fa-shield me-1"></i>activée</span>'
                    : '<span class="badge bg-warning-subtle text-warning-emphasis"><i class="fas fa-shield-halved me-1"></i>désactivée</span>';
                $flags = [];
                if ($u->password_legacy)   { $flags[] = '<span class="badge bg-secondary-subtle">SHA1</span>'; }
                if ($u->must_set_password) { $flags[] = '<span class="badge bg-info-subtle">activation</span>'; }

                return [
                    'id'        => $u->id,
                    'nom'       => e($u->nom . ' ' . $u->prenom),
                    'email'     => e($u->email ?? '—'),
                    'telephone' => '<code>' . e($u->telephone ?? '—') . '</code>',
                    'roles'     => e($roles) . ' ' . implode(' ', $flags),
                    'twofa'     => $twofa,
                    'last_login_at' => $u->last_login_at?->diffForHumans() ?? '<span class="text-muted">jamais</span>',
                    'actions'   => sprintf(
                        '<a href="%s" class="btn btn-sm btn-outline-primary">Voir</a>',
                        e(route('admin.pages.users.show', $u->id)),
                    ),
                ];
            })
            ->respond($request);
    }

    public function show(Request $request, User $user): View
    {
        $this->ensure($request, 'view:users:*');

        $user->loadMissing(['roles:id,code,name']);

        $loginAttempts = DB::table('login_attempts')
            ->where('user_id', $user->id)
            ->orderByDesc('attempted_at')
            ->limit(20)
            ->get(['ip_address', 'user_agent', 'succeeded', 'failure_reason', 'attempted_at']);

        return view('usermanagement::admin.users.show', [
            'user'          => $user,
            'allRoles'      => Role::query()->orderBy('name')->get(['id', 'code', 'name', 'description']),
            'assignedRoles' => $user->roles->pluck('id')->all(),
            'loginAttempts' => $loginAttempts,
            'canEdit'       => $this->checker->can($request->user(), 'edit:users:*'),
        ]);
    }

    public function syncRoles(Request $request, User $user): RedirectResponse
    {
        $this->ensure($request, 'edit:users:*');

        $data = Validator::validate($request->all(), [
            'role_ids'   => ['nullable', 'array'],
            'role_ids.*' => ['uuid', 'exists:roles,id'],
        ]);
        $user->roles()->sync($data['role_ids'] ?? []);
        $user->flushPermissionsCache();

        return back()->with('status', 'Rôles mis à jour.');
    }

    public function reset2fa(Request $request, User $user): RedirectResponse
    {
        $this->ensure($request, 'edit:users:*');

        $user->forceFill([
            'google2fa_secret'       => null,
            'google2fa_confirmed_at' => null,
        ])->save();

        return back()->with('status', "La double authentification de {$user->prenom} {$user->nom} a été réinitialisée. L'utilisateur devra réenrôler à sa prochaine connexion.");
    }

    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        $this->ensure($request, 'edit:users:*');

        // Two modes:
        //   1. mode=temp_password (default) — generate a 12-char temp password,
        //      hash it, set must_set_password=true so the user is forced to
        //      change it on first login. The plaintext is flashed back ONCE
        //      so the admin can hand it over (offline / phone / signal).
        //   2. mode=activation — clear the password, send the user through
        //      the first-login wizard (email + tel verification).
        $mode = $request->input('mode', 'temp_password');

        if ($mode === 'activation') {
            DB::table('users')->where('id', $user->id)->update([
                'password'          => null,
                'password_legacy'   => false,
                'must_set_password' => true,
                'updated_at'        => now(),
            ]);
            return back()->with('status', "Mot de passe invalidé — l'utilisateur devra le redéfinir via la première connexion.");
        }

        // Temp-password mode (default).
        $temp = $this->generateTempPassword();
        DB::table('users')->where('id', $user->id)->update([
            'password'          => Hash::make($temp),
            'password_legacy'   => false,
            'must_set_password' => true,
            'updated_at'        => now(),
        ]);

        return back()
            ->with('status', "Mot de passe temporaire généré. À transmettre une seule fois&nbsp;:")
            ->with('temp_password', $temp);
    }

    /**
     * Block or unblock an account. Blocked users cannot pass login — they
     * still appear in lists / audits but the auth pipeline short-circuits
     * before the password check.
     *
     *   POST /admin/users/{user}/toggle-block
     *        reason (optional, max 500) — captured when blocking
     */
    public function toggleBlock(Request $request, User $user): RedirectResponse
    {
        $this->ensure($request, 'edit:users:*');

        // Guard: an admin cannot lock themselves out.
        if ((string) $user->getKey() === (string) $request->user()?->getKey()) {
            return back()->withErrors(['blocked_at' => 'Vous ne pouvez pas bloquer votre propre compte.']);
        }

        if ($user->isBlocked()) {
            $user->forceFill([
                'blocked_at'         => null,
                'blocked_reason'     => null,
                'blocked_by_user_id' => null,
            ])->save();
            return back()->with('status', "{$user->prenom} {$user->nom} a été débloqué.");
        }

        $reason = (string) $request->input('reason', '');
        $user->forceFill([
            'blocked_at'         => now(),
            'blocked_reason'     => $reason !== '' ? mb_substr($reason, 0, 500) : null,
            'blocked_by_user_id' => $request->user()?->getKey(),
        ])->save();

        return back()->with('status', "{$user->prenom} {$user->nom} a été bloqué.");
    }

    private function ensure(Request $request, string $permission): void
    {
        if (! $this->checker->can($request->user(), $permission)) {
            abort(Response::HTTP_FORBIDDEN);
        }
    }

    /**
     * Cryptographically-strong 12-char temp password. Mixed case + digits,
     * no ambiguous chars (0/O/1/l) so admins reading it aloud over the phone
     * don't fumble.
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
