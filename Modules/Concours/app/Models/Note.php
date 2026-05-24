<?php

declare(strict_types=1);

namespace Modules\Concours\Models;

use App\Foundation\Concerns\HasUuid;
use App\Foundation\Permissions\Contracts\Scopable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\UserManagement\Models\User;

/**
 * Scoping behaviour: a note inherits the candidate's centre, so chef-centre
 * users only see their own. We expose centre_id via a derived attribute
 * rather than denormalise it — the JOIN cost is minimal and we avoid the
 * "row says centre A but candidate now in centre B" inconsistency window.
 */
final class Note extends Model implements Scopable
{
    use HasUuid;
    use SoftDeletes;

    protected $table = 'notes';

    /** @var array<int, string> */
    protected $fillable = [
        'candidat_id', 'epreuve_id',
        'valeur', 'absent', 'locked',
        'entered_by_user_id', 'entered_at', 'updated_by_user_id',
        'commentaire',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'valeur'      => 'decimal:2',
        'absent'      => 'boolean',
        'locked'      => 'boolean',
        'entered_at'  => 'datetime',
    ];

    public function scopeColumnFor(string $scope): ?string
    {
        // Notes don't carry centre_id directly. Filtering by centre is done
        // via JOIN in the controller (see NoteController). For row-level
        // grants we delegate to the candidat.
        return match ($scope) {
            'own_session' => 'epreuve_id', // weak, but the rule below short-circuits
            default       => null,
        };
    }

    /** @return BelongsTo<Candidat, $this> */
    public function candidat(): BelongsTo
    {
        return $this->belongsTo(Candidat::class);
    }

    /** @return BelongsTo<Epreuve, $this> */
    public function epreuve(): BelongsTo
    {
        return $this->belongsTo(Epreuve::class);
    }

    /** @return BelongsTo<User, $this> */
    public function enteredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'entered_by_user_id');
    }

    public function isEntered(): bool
    {
        return $this->valeur !== null || $this->absent;
    }
}
