<?php

declare(strict_types=1);

namespace Modules\Concours\Models;

use App\Foundation\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\AcademicStructure\Models\Cycle;
use Modules\AcademicStructure\Models\Section;
use Modules\Referentiels\Models\TypeEpreuve;

final class Epreuve extends Model
{
    use HasUuid;
    use SoftDeletes;

    public const SCOPE_CYCLE   = 'cycle';
    public const SCOPE_SECTION = 'section';

    protected $table = 'epreuves';

    /** @var array<int, string> */
    protected $fillable = [
        'concours_session_id', 'type_epreuve_id',
        'code', 'libelle', 'description',
        'scope_type', 'scope_id',
        'coefficient', 'duree_minutes', 'note_max',
        'ordre', 'active',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'coefficient'    => 'decimal:2',
        'duree_minutes'  => 'integer',
        'note_max'       => 'decimal:2',
        'ordre'          => 'integer',
        'active'         => 'boolean',
    ];

    /** @return BelongsTo<ConcoursSession, $this> */
    public function session(): BelongsTo
    {
        return $this->belongsTo(ConcoursSession::class, 'concours_session_id');
    }

    /** @return BelongsTo<TypeEpreuve, $this> */
    public function typeEpreuve(): BelongsTo
    {
        return $this->belongsTo(TypeEpreuve::class);
    }

    /** @return HasMany<EpreuvePlanning> */
    public function plannings(): HasMany
    {
        return $this->hasMany(EpreuvePlanning::class);
    }

    /** @return HasMany<Note> */
    public function notes(): HasMany
    {
        return $this->hasMany(Note::class);
    }

    /**
     * Sections (de concours) this épreuve applies to. The pivot
     * `epreuve_sections` is the SOURCE OF TRUTH for eligibility / moyenne /
     * emploi du temps; the legacy scope_type/scope_id columns are kept only for
     * backward compatibility and were backfilled into this pivot.
     *
     * @return BelongsToMany<Section>
     */
    public function sections(): BelongsToMany
    {
        return $this->belongsToMany(Section::class, 'epreuve_sections', 'epreuve_id', 'section_id')
            ->withTimestamps();
    }

    /**
     * Section ids this épreuve targets (loaded relation aware, no N+1 when
     * eager-loaded).
     *
     * @return list<string>
     */
    public function sectionIds(): array
    {
        $ids = $this->relationLoaded('sections')
            ? $this->sections->pluck('id')
            : $this->sections()->pluck('sections.id');

        return $ids->map(static fn ($v): string => (string) $v)->all();
    }

    public function scopeOf(): Cycle|Section|null
    {
        return match ($this->scope_type) {
            self::SCOPE_CYCLE   => Cycle::query()->find($this->scope_id),
            self::SCOPE_SECTION => Section::query()->find($this->scope_id),
            default             => null,
        };
    }

    /**
     * Candidats expected to take this épreuve: those whose FIRST choice is one
     * of the épreuve's sections. Driven by the epreuve_sections pivot.
     */
    public function eligibleCandidatsQuery(): Builder
    {
        $base       = Candidat::query()->where('concours_session_id', $this->concours_session_id);
        $sectionIds = $this->sectionIds();

        return $sectionIds === []
            ? $base->whereRaw('1 = 0')
            : $base->whereIn('section_premier_choix_id', $sectionIds);
    }

    /** @return array<string, list<string>> */
    public static function validationRules(): array
    {
        return [
            'concours_session_id' => ['required', 'uuid', 'exists:concours_sessions,id'],
            'type_epreuve_id'     => ['required', 'uuid', 'exists:types_epreuves,id'],
            'code'                => ['required', 'string', 'max:30'],
            'libelle'             => ['required', 'string', 'max:191'],
            'description'         => ['nullable', 'string'],
            // Épreuve now targets one OR many sections (pivot). scope_* kept
            // nullable purely for backward compatibility.
            'sections'            => ['required', 'array', 'min:1'],
            'sections.*'          => ['uuid', 'exists:sections,id'],
            'scope_type'          => ['nullable', 'in:cycle,section'],
            'scope_id'            => ['nullable', 'uuid'],
            'coefficient'         => ['required', 'numeric', 'min:0.1', 'max:10'],
            'duree_minutes'       => ['required', 'integer', 'min:5', 'max:600'],
            'note_max'            => ['sometimes', 'numeric', 'min:1', 'max:100'],
            'ordre'               => ['sometimes', 'integer', 'min:0'],
            'active'              => ['sometimes', 'boolean'],
        ];
    }
}
