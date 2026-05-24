<?php

declare(strict_types=1);

namespace Modules\Parametrage\Models;

use App\Foundation\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A single configuration entry.
 *
 * The raw `value` column is always plain TEXT; encryption (when
 * `is_encrypted=true`) is applied at the application boundary in
 * SettingValueCaster so seeders, fixtures, and audit logs stay readable
 * for non-secret settings.
 */
final class Setting extends Model
{
    use HasUuid;
    use SoftDeletes;

    protected $table = 'settings';

    /** @var array<int, string> */
    protected $fillable = [
        'key',
        'category',
        'type',
        'value',
        'default_value',
        'label',
        'description',
        'validation_rules',
        'is_encrypted',
        'is_public',
        'is_system',
        'display_order',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'validation_rules' => 'array',
        'is_encrypted'     => 'boolean',
        'is_public'        => 'boolean',
        'is_system'        => 'boolean',
        'display_order'    => 'integer',
    ];

    /** @return HasMany<SettingChangeLog> */
    public function changeLogs(): HasMany
    {
        return $this->hasMany(SettingChangeLog::class);
    }
}
