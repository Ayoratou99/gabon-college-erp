<?php

declare(strict_types=1);

namespace Modules\Concours\Models;

use App\Foundation\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Referentiels\Models\DocumentRequis;
use Modules\UserManagement\Models\User;

final class CandidatDocument extends Model
{
    use HasUuid;
    use SoftDeletes;

    public const REVIEW_PENDING  = 'en_attente';
    public const REVIEW_APPROVED = 'valide';
    public const REVIEW_REJECTED = 'a_refaire';

    protected $table = 'candidat_documents';

    /** @var array<int, string> */
    protected $fillable = [
        'candidat_id', 'document_requis_id',
        'file_path', 'disk', 'mime_type',
        'size_bytes', 'original_name', 'sha256',
        'uploaded_at',
        'review_status', 'reviewed_at', 'reviewed_by_user_id', 'review_comment',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'size_bytes'   => 'integer',
        'uploaded_at'  => 'datetime',
        'reviewed_at'  => 'datetime',
    ];

    /** @return BelongsTo<Candidat, $this> */
    public function candidat(): BelongsTo
    {
        return $this->belongsTo(Candidat::class);
    }

    /** @return BelongsTo<DocumentRequis, $this> */
    public function documentRequis(): BelongsTo
    {
        return $this->belongsTo(DocumentRequis::class);
    }

    /** @return BelongsTo<User, $this> */
    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    public function isApproved(): bool { return $this->review_status === self::REVIEW_APPROVED; }
    public function isRejected(): bool { return $this->review_status === self::REVIEW_REJECTED; }
    public function isPending(): bool  { return $this->review_status === self::REVIEW_PENDING; }

    /**
     * Effective size in bytes, falling back to the real on-disk size when the
     * stored `size_bytes` is 0.
     *
     * Legacy documents were imported from the old DB *before* their files were
     * uploaded to prod (the import ran on a machine where documentcupk/ was
     * empty), so the column is 0 even though the file now exists on the
     * `legacy` disk. Reading the size at render time is self-healing — no
     * re-import or DB backfill required, and it costs one stat() per document.
     */
    public function effectiveSizeBytes(): int
    {
        $stored = (int) ($this->size_bytes ?? 0);
        if ($stored > 0) {
            return $stored;
        }

        if (empty($this->file_path)) {
            return 0;
        }

        try {
            $disk = \Illuminate\Support\Facades\Storage::disk($this->disk ?: 'local');

            return $disk->exists($this->file_path) ? (int) $disk->size($this->file_path) : 0;
        } catch (\Throwable) {
            return 0;
        }
    }
}
