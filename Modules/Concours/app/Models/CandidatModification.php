<?php

declare(strict_types=1);

namespace Modules\Concours\Models;

use App\Foundation\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\UserManagement\Models\User;

final class CandidatModification extends Model
{
    use HasUuid;

    public const CHANNEL_PUBLIC = 'public';
    public const CHANNEL_ADMIN  = 'admin';
    public const CHANNEL_SYSTEM = 'system';

    protected $table = 'candidat_modifications';

    /** @var array<int, string> */
    protected $fillable = [
        'candidat_id', 'user_id', 'channel',
        'field', 'old_value', 'new_value', 'reason',
        'ip_address', 'changed_at',
    ];

    /** @var array<string, string> */
    protected $casts = ['changed_at' => 'datetime'];

    /** @return BelongsTo<Candidat, $this> */
    public function candidat(): BelongsTo
    {
        return $this->belongsTo(Candidat::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
