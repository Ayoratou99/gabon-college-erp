<?php

declare(strict_types=1);

namespace Modules\Concours\Models;

use App\Foundation\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\UserManagement\Models\User;

final class ChefCentreAssignment extends Model
{
    use HasUuid;
    use SoftDeletes;

    protected $table = 'chef_centre_assignments';

    /** @var array<int, string> */
    protected $fillable = [
        'concours_session_id', 'centre_id', 'user_id',
        'est_principal', 'assigned_at', 'assigned_by_user_id',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'est_principal' => 'boolean',
        'assigned_at'   => 'datetime',
    ];

    /** @return BelongsTo<ConcoursSession, $this> */
    public function session(): BelongsTo
    {
        return $this->belongsTo(ConcoursSession::class, 'concours_session_id');
    }

    /** @return BelongsTo<Centre, $this> */
    public function centre(): BelongsTo
    {
        return $this->belongsTo(Centre::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
