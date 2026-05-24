<?php

declare(strict_types=1);

namespace Modules\Concours\Models;

use App\Foundation\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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

    public function scopeOf(): Cycle|Section|null
    {
        return match ($this->scope_type) {
            self::SCOPE_CYCLE   => Cycle::query()->find($this->scope_id),
            self::SCOPE_SECTION => Section::query()->find($this->scope_id),
            default             => null,
        };
    }

    /**
     * Candidats expected to take this epreuve, given its scope.
     */
    public function eligibleCandidatsQuery(): Builder
    {
        $base = Candidat::query()->where('concours_session_id', $this->concours_session_id);

        return match ($this->scope_type) {
            self::SCOPE_SECTION => $base->where('section_premier_choix_id', $this->scope_id),
            self::SCOPE_CYCLE   => $base->whereHas(
                'premierChoix',
                fn (Builder $q) => $q->where('cycle_id', $this->scope_id),
            ),
            default             => $base->whereRaw('1 = 0'),
        };
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
            'scope_type'          => ['required', 'in:cycle,section'],
            'scope_id'            => ['required', 'uuid'],
            'coefficient'         => ['required', 'numeric', 'min:0.1', 'max:10'],
            'duree_minutes'       => ['required', 'integer', 'min:5', 'max:600'],
            'note_max'            => ['sometimes', 'numeric', 'min:1', 'max:100'],
            'ordre'               => ['sometimes', 'integer', 'min:0'],
            'active'              => ['sometimes', 'boolean'],
        ];
    }
}
