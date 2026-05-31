<?php

declare(strict_types=1);

namespace Modules\Parametrage\Models;

use App\Foundation\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\UserManagement\Models\User;

final class SettingChangeLog extends Model
{
    use HasUuid;

    protected $table = 'setting_change_logs';
    public $timestamps = false;

    /** @var array<int, string> */
    protected $fillable = [
        'setting_id',
        'user_id',
        'old_value',
        'new_value',
        'ip_address',
        'changed_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'changed_at' => 'datetime',
    ];

    /** @return BelongsTo<Setting, $this> */
    public function setting(): BelongsTo
    {
        return $this->belongsTo(Setting::class);
    }

    /**
     * The user who flipped the value. Null for console / queue / system
     * writes (the writer in SettingsService allows that).
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
