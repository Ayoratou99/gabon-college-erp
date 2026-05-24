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
        'google2fa_secret',
        'google2fa_confirmed_at',
        'last_login_at',
        'last_login_ip',
        'current_session_id',
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
        'google2fa_secret'       => 'encrypted',
        'google2fa_confirmed_at' => 'datetime',
        'last_login_at'          => 'datetime',
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
        $key = "cuk:perm:user:{$this->getKey()}";

        /** @var array<int, string> $patterns */
        $patterns = Cache::store(config('permissions.cache.store', 'redis'))
            ->remember(
                $key,
                (int) config('permissions.cache.ttl', 3600),
                fn (): array => $this->roles()
                    ->with('permissions:id,pattern')
                    ->get()
                    ->pluck('permissions')
                    ->flatten()
                    ->pluck('pattern')
                    ->unique()
                    ->values()
                    ->all(),
            );

        return collect($patterns)
            ->map(fn (string $pattern): PermissionValueObject => PermissionValueObject::parse($pattern));
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

    public function flushPermissionsCache(): void
    {
        Cache::store(config('permissions.cache.store', 'redis'))
            ->forget("cuk:perm:user:{$this->getKey()}");
    }

    public function getAuthIdentifierName(): string
    {
        return 'id';
    }
}
