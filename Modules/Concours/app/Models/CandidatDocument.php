<?php

declare(strict_types=1);

namespace Modules\Concours\Models;

use App\Foundation\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Referentiels\Models\DocumentRequis;

final class CandidatDocument extends Model
{
    use HasUuid;
    use SoftDeletes;

    protected $table = 'candidat_documents';

    /** @var array<int, string> */
    protected $fillable = [
        'candidat_id', 'document_requis_id',
        'file_path', 'disk', 'mime_type',
        'size_bytes', 'original_name', 'sha256',
        'uploaded_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'size_bytes'   => 'integer',
        'uploaded_at'  => 'datetime',
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
}
