<?php

declare(strict_types=1);

namespace Modules\UserManagement\Models;

use App\Foundation\Concerns\HasUuid;
use App\Foundation\Permissions\Permission as PermissionValueObject;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Catalog row for a single permission pattern.
 *
 * The string in `pattern` is the *granted* form (e.g. `edit:candidats:own_center`)
 * and is parsed on demand to an immutable value object.
 *
 * Soft-deletes are intentionally OFF on this table: permissions describe the
 * universe of capabilities — when a pattern is removed it must vanish, so
 * lingering soft-deleted rows can't silently grant access on re-assignment.
 */
final class Permission extends Model
{
    use HasUuid;

    protected $table = 'permissions';

    /** @var array<int, string> */
    protected $fillable = ['pattern', 'module', 'label', 'description'];

    public function toValueObject(): PermissionValueObject
    {
        return PermissionValueObject::parse($this->pattern);
    }

    /** @return BelongsToMany<Role> */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_permission')
            ->withTimestamps();
    }
}
