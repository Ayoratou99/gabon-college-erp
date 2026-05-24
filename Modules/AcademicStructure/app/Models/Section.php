<?php

declare(strict_types=1);

namespace Modules\AcademicStructure\Models;

use App\Foundation\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Referentiels\Concerns\ReferentielModel;

/**
 * A *formation* (academic programme), e.g. "DUT Chimie Industrielle".
 *
 * Belongs to a Cycle (mandatory) and optionally a Departement. The Concours
 * module (Stage 5) references this table for `candidats.section_id` and
 * `candidats.section_id_2` (premier et second voeu).
 */
final class Section extends Model
{
    use HasUuid;
    use ReferentielModel;
    use SoftDeletes;

    protected $table = 'sections';

    /** @var array<int, string> */
    protected $fillable = [
        'cycle_id', 'departement_id',
        'code', 'nom', 'description',
        'places_par_session', 'ouvert_au_concours',
        'active', 'display_order',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'places_par_session' => 'integer',
        'ouvert_au_concours' => 'boolean',
        'active'             => 'boolean',
        'display_order'      => 'integer',
    ];

    /** @return BelongsTo<Cycle, $this> */
    public function cycle(): BelongsTo
    {
        return $this->belongsTo(Cycle::class);
    }

    /** @return BelongsTo<Departement, $this> */
    public function departement(): BelongsTo
    {
        return $this->belongsTo(Departement::class);
    }

    /** @return array<string, list<string>> */
    public static function validationRules(): array
    {
        return [
            'cycle_id'           => ['required', 'uuid', 'exists:cycles,id'],
            'departement_id'     => ['nullable', 'uuid', 'exists:departements,id'],
            'code'               => ['required', 'string', 'max:20'],
            'nom'                => ['required', 'string', 'max:191'],
            'description'        => ['nullable', 'string'],
            'places_par_session' => ['sometimes', 'integer', 'min:1', 'max:10000'],
            'ouvert_au_concours' => ['sometimes', 'boolean'],
            'active'             => ['sometimes', 'boolean'],
            'display_order'      => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
