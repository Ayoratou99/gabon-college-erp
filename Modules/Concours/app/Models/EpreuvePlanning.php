<?php

declare(strict_types=1);

namespace Modules\Concours\Models;

use App\Foundation\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\AcademicStructure\Models\Salle;

final class EpreuvePlanning extends Model
{
    use HasUuid;
    use SoftDeletes;

    protected $table = 'epreuve_plannings';

    /** @var array<int, string> */
    protected $fillable = [
        'epreuve_id', 'concours_session_centre_id', 'salle_id',
        'date_epreuve', 'heure_debut', 'heure_fin', 'consigne',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'date_epreuve' => 'date',
        'heure_debut'  => 'string', // keep as HH:MM:SS — datetime cast complicates comparisons
        'heure_fin'    => 'string',
    ];

    /** @return BelongsTo<Epreuve, $this> */
    public function epreuve(): BelongsTo
    {
        return $this->belongsTo(Epreuve::class);
    }

    /** @return BelongsTo<Salle, $this> */
    public function salle(): BelongsTo
    {
        return $this->belongsTo(Salle::class);
    }

    /**
     * Returns true when this planning overlaps `$other` on the same date+room.
     */
    public function overlapsWith(self $other): bool
    {
        if (! $this->date_epreuve->equalTo($other->date_epreuve)) {
            return false;
        }
        if ($this->salle_id === null || $other->salle_id === null) {
            return false;
        }
        if ($this->salle_id !== $other->salle_id) {
            return false;
        }
        // [a, b) vs [c, d) overlap iff a < d && c < b
        return $this->heure_debut < $other->heure_fin
            && $other->heure_debut < $this->heure_fin;
    }
}
