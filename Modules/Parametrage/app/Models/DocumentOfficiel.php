<?php

declare(strict_types=1);

namespace Modules\Parametrage\Models;

use App\Foundation\Concerns\HasUuid;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

/**
 * An official document (procès-verbal, règlement, présentation…) surfaced on
 * the public /documents-officiels page and the footer. Managed via a real
 * back-office CRUD (replaces the old JSON `site.documents_officiels` setting).
 */
final class DocumentOfficiel extends Model
{
    use HasUuid;
    use SoftDeletes;

    protected $table = 'documents_officiels';

    /** @var array<int, string> */
    protected $fillable = [
        'title', 'file_path', 'file_disk', 'mime_type', 'size_bytes', 'display_order', 'active',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'size_bytes'    => 'integer',
        'display_order' => 'integer',
        'active'        => 'boolean',
    ];

    public function isPdf(): bool
    {
        return $this->mime_type === 'application/pdf'
            || str_ends_with(mb_strtolower((string) $this->file_path), '.pdf');
    }

    public function disk(): Filesystem
    {
        return Storage::disk($this->file_disk ?: 'public');
    }
}
