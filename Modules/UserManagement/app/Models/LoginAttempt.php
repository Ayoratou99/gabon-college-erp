<?php

declare(strict_types=1);

namespace Modules\UserManagement\Models;

use App\Foundation\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class LoginAttempt extends Model
{
    use HasUuid;

    protected $table = 'login_attempts';
    public $timestamps = false; // we only store attempted_at

    /** @var array<int, string> */
    protected $fillable = [
        'identifier',
        'ip_address',
        'user_agent',
        'user_id',
        'succeeded',
        'failure_reason',
        'attempted_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'succeeded'    => 'boolean',
        'attempted_at' => 'datetime',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
