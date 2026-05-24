<?php

declare(strict_types=1);

namespace Modules\Concours\Models;

use App\Foundation\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Payment extends Model
{
    use HasUuid;
    use SoftDeletes;

    public const STATUS_INIT    = 'INIT';
    public const STATUS_PENDING = 'PENDING';
    public const STATUS_PAID    = 'PAID';
    public const STATUS_FAILED  = 'FAILED';

    protected $table = 'payments';

    /** @var array<int, string> */
    protected $fillable = [
        'candidat_id', 'concours_session_id',
        'amount', 'currency',
        'ebilling_id', 'external_reference', 'status',
        'payload', 'paid_at', 'callback_ip', 'signature_verified',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'amount'             => 'integer',
        'payload'            => 'array',
        'paid_at'            => 'datetime',
        'signature_verified' => 'boolean',
    ];

    /** @return BelongsTo<Candidat, $this> */
    public function candidat(): BelongsTo
    {
        return $this->belongsTo(Candidat::class);
    }

    /** @return BelongsTo<ConcoursSession, $this> */
    public function session(): BelongsTo
    {
        return $this->belongsTo(ConcoursSession::class, 'concours_session_id');
    }

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }
}
