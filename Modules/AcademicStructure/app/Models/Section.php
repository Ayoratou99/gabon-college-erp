<?php

declare(strict_types=1);

namespace Modules\AcademicStructure\Models;

use App\Foundation\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Referentiels\Concerns\ReferentielModel;
use Modules\Referentiels\Models\DocumentRequis;

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
        'code', 'nom', 'description', 'image_url',
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

    /**
     * Section-specific document requirements. The pivot has no extra
     * payload — presence in the table is the only signal we need.
     *
     * @return BelongsToMany<DocumentRequis>
     */
    public function documentsRequis(): BelongsToMany
    {
        return $this->belongsToMany(
            DocumentRequis::class,
            'documents_requis_sections',
            'section_id',
            'document_requis_id',
        )->withTimestamps();
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
            'image_url'          => ['nullable', 'string', 'max:500'],
            'places_par_session' => ['sometimes', 'integer', 'min:1', 'max:10000'],
            'ouvert_au_concours' => ['sometimes', 'boolean'],
            'active'             => ['sometimes', 'boolean'],
            'display_order'      => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
