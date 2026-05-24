<?php

declare(strict_types=1);

namespace Modules\Concours\Models;

use App\Foundation\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\UserManagement\Models\User;

final class ResultPublication extends Model
{
    use HasUuid;
    use SoftDeletes;

    protected $table = 'result_publications';

    /** @var array<int, string> */
    protected $fillable = [
        'concours_session_id', 'published_by_user_id',
        'published_at',
        'total_candidats', 'total_admis', 'breakdown_par_section',
        'fichier_path', 'fichier_disk',
        'communique', 'active',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'published_at'           => 'datetime',
        'breakdown_par_section'  => 'array',
        'total_candidats'        => 'integer',
        'total_admis'            => 'integer',
        'active'                 => 'boolean',
    ];

    /** @return BelongsTo<ConcoursSession, $this> */
    public function session(): BelongsTo
    {
        return $this->belongsTo(ConcoursSession::class, 'concours_session_id');
    }

    /** @return BelongsTo<User, $this> */
    public function publishedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by_user_id');
    }

    public static function latestActiveFor(string $sessionId): ?self
    {
        return static::query()
            ->where('concours_session_id', $sessionId)
            ->where('active', true)
            ->latest('published_at')
            ->first();
    }
}
