<?php

declare(strict_types=1);

namespace App\Foundation\Models;

use App\Foundation\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Base Eloquent model for all domain models in this app.
 *
 *   - UUID v7 primary key (HasUuid trait)
 *   - Soft deletes everywhere (deleted_at)
 *   - Strict attribute filling (no $guarded shortcuts; declare $fillable explicitly)
 *   - Snake-case timestamps already default in Laravel
 *
 * Reference data (provinces, nationalites, etc.) MAY skip soft deletes by
 * overriding `$dates` and not using DELETE in business code. The trait stays
 * but never triggers unless someone calls ->delete().
 *
 * Models that need scope-based RBAC (centre_id / region_id / session_id columns)
 * additionally implement App\Foundation\Permissions\Contracts\Scopable.
 */
abstract class BaseModel extends Model
{
    use HasUuid;
    use SoftDeletes;

    /**
     * Force explicit fillable lists in every subclass — refuse mass-assign by default.
     *
     * @var array<int, string>
     */
    protected $guarded = ['id'];

    /** @var array<int, string> */
    protected $hidden = ['deleted_at'];

    public function getRouteKeyName(): string
    {
        return 'id';
    }
}
