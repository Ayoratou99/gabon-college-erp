<?php

declare(strict_types=1);

namespace Modules\UserManagement\Models;

use App\Foundation\Concerns\HasUuid;
use App\Foundation\Identity\Contracts\UserScopeResolver;
use App\Foundation\Permissions\Contracts\PermissionHolder;
use App\Foundation\Permissions\Permission as PermissionValueObject;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\HasApiTokens;

/**
 * The real User model lives here (not in App\Models) so the UserManagement
 * module owns its own data + relations. App\Models\User is a thin alias to
 * keep Laravel's default auth provider happy.
 *
 * Implements PermissionHolder so the RBAC engine can grant / scope queries.
 */
class User extends Authenticatable implements PermissionHolder
{
    use HasApiTokens;
    use HasUuid;
    use Notifiable;
    use SoftDeletes;

    protected $table = 'users';

    /** @var array<int, string> */
    protected $fillable = [
        'nom',
        'prenom',
        'email',
        'telephone',
        'password',
        'password_legacy',
        'must_set_password',
        'google2fa_secret',
        'google2fa_confirmed_at',
        'last_login_at',
        'last_login_ip',
        'blocked_at',
        'blocked_reason',
        'blocked_by_user_id',
        'current_session_id',
        'promoted_from_candidat_id',
    ];

    /** @var array<int, string> */
    protected $hidden = [
        'password',
        'remember_token',
        'google2fa_secret',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'password'               => 'hashed',
        'password_legacy'        => 'boolean',
        'must_set_password'      => 'boolean',
        'google2fa_secret'       => 'encrypted',
        'google2fa_confirmed_at' => 'datetime',
        'last_login_at'          => 'datetime',
        'blocked_at'             => 'datetime',
    ];

    // ----------------------------------------------------------------
    // Relations
    // ----------------------------------------------------------------

    /** @return BelongsToMany<Role> */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_role')->withTimestamps();
    }

    /** @return HasMany<LoginAttempt> */
    public function loginAttempts(): HasMany
    {
        return $this->hasMany(LoginAttempt::class);
    }

    // ----------------------------------------------------------------
    // PermissionHolder
    // ----------------------------------------------------------------

    public function permissions(): Collection
    {
        // Multi-role users pick which "hat" they're wearing at login
        // (RolePickerController stores the chosen role id in the session).
        // When an active role is set, ONLY that role's permissions are
        // returned — switching roles is real, not cosmetic.
        //
        // No active role in session ⇒ aggregate across every role the user
        // holds. That's the case for: CLI runs (artisan tinker, queues),
        // API tokens, and the brief window between auth and picker.
        $activeRoleId = $this->activeRoleId();
        $cacheKey     = "cuk:perm:user:{$this->getKey()}:role:" . ($activeRoleId ?? 'all');

        /** @var array<int, string> $patterns */
        $patterns = Cache::store(config('permissions.cache.store', config('cache.default')))
            ->remember(
                $cacheKey,
                (int) config('permissions.cache.ttl', 3600),
                function () use ($activeRoleId): array {
                    $query = $this->roles()->with('permissions:id,pattern');
                    if ($activeRoleId !== null) {
                        $query->where('roles.id', $activeRoleId);
                    }
                    return $query->get()
                        ->pluck('permissions')
                        ->flatten()
                        ->pluck('pattern')
                        ->unique()
                        ->values()
                        ->all();
                },
            );

        return collect($patterns)
            ->map(fn (string $pattern): PermissionValueObject => PermissionValueObject::parse($pattern));
    }

    /**
     * The role this user picked at the role-picker step (if any).
     * Reads from the HTTP session — defensive: returns null when there's
     * no session bound (CLI, queues, API token requests).
     */
    public function activeRoleId(): ?string
    {
        if (! app()->bound('session.store')) {
            return null;
        }
        try {
            $session = app('session.store');
            if (! $session->isStarted()) {
                return null;
            }
            $id = $session->get('cuk.active_role_id');
            return is_string($id) ? $id : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * The Role object matching the user's active role id, or null.
     * Used by the topbar to display "you are currently DG / DE / …" and
     * by RolePickerController for self-checks.
     */
    public function activeRole(): ?Role
    {
        $id = $this->activeRoleId();
        if ($id === null) {
            return null;
        }
        return $this->roles->firstWhere('id', $id);
    }

    /**
     * Delegated to the bound UserScopeResolver. The default (no-op) returns
     * an empty list; Concours module overrides it to query
     * chef_centre_assignments for the user's current/active session.
     *
     * @return array<int, string>
     */
    public function accessibleCentreIds(): array
    {
        return app(UserScopeResolver::class)->accessibleCentreIds($this);
    }

    /** @return array<int, string> */
    public function accessibleRegionIds(): array
    {
        return app(UserScopeResolver::class)->accessibleRegionIds($this);
    }

    public function currentSessionId(): ?string
    {
        return $this->current_session_id;
    }

    // ----------------------------------------------------------------
    // Auth helpers
    // ----------------------------------------------------------------

    public function hasRole(string $code): bool
    {
        return $this->roles->contains(fn (Role $r): bool => $r->code === $code);
    }

    public function isBlocked(): bool
    {
        return $this->blocked_at !== null;
    }

    public function hasAnyRole(string ...$codes): bool
    {
        return $this->roles->contains(fn (Role $r): bool => in_array($r->code, $codes, true));
    }

    public function needsTwoFactorEnrollment(): bool
    {
        return $this->google2fa_secret === null || $this->google2fa_confirmed_at === null;
    }

    public function isLegacyPassword(): bool
    {
        return $this->password_legacy === true;
    }

    /**
     * True if this account hasn't completed first-login activation:
     * either flagged explicitly via must_set_password, or no password
     * has ever been stored.
     */
    public function needsActivation(): bool
    {
        return $this->must_set_password === true
            || $this->getAttribute('password') === null;
    }

    public function flushPermissionsCache(): void
    {
        Cache::store(config('permissions.cache.store', config('cache.default')))
            ->forget("cuk:perm:user:{$this->getKey()}");
    }

    public function getAuthIdentifierName(): string
    {
        return 'id';
    }
}
