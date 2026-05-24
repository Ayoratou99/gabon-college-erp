<?php

declare(strict_types=1);

namespace Modules\UserManagement\Models;

use App\Foundation\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Role extends Model
{
    use HasUuid;
    use SoftDeletes;

    protected $table = 'roles';

    /** @var array<int, string> */
    protected $fillable = ['code', 'name', 'description', 'is_system'];

    protected $casts = [
        'is_system' => 'boolean',
    ];

    /** @return BelongsToMany<Permission> */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permission')
            ->withTimestamps();
    }

    /** @return BelongsToMany<User> */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_role')
            ->withTimestamps();
    }
}
