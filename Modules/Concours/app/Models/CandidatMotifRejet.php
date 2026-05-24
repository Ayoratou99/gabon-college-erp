<?php

declare(strict_types=1);

namespace Modules\Concours\Models;

use App\Foundation\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\UserManagement\Models\User;

final class CandidatMotifRejet extends Model
{
    use HasUuid;
    use SoftDeletes;

    protected $table = 'candidat_motifs_rejet';

    /** @var array<int, string> */
    protected $fillable = ['candidat_id', 'motif', 'decided_by_user_id', 'decided_at'];

    /** @var array<string, string> */
    protected $casts = ['decided_at' => 'datetime'];

    /** @return BelongsTo<Candidat, $this> */
    public function candidat(): BelongsTo
    {
        return $this->belongsTo(Candidat::class);
    }

    /** @return BelongsTo<User, $this> */
    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by_user_id');
    }
}
