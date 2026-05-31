<?php

declare(strict_types=1);

namespace Modules\Concours\Models;

use App\Foundation\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Custom pivot for concours_session_centres so Laravel auto-generates the
 * UUID primary key on `sync()` / `attach()` calls. Plain Pivot wouldn't —
 * pivots default to incrementing-int PK.
 */
final class ConcoursSessionCentre extends Pivot
{
    use HasUuid;

    protected $table = 'concours_session_centres';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = true;

    /** @var array<int, string> */
    protected $fillable = ['concours_session_id', 'centre_id', 'lieu_concours', 'capacite_override', 'active'];
}
