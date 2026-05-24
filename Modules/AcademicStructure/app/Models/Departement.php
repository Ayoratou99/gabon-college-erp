<?php

declare(strict_types=1);

namespace Modules\AcademicStructure\Models;

use App\Foundation\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Referentiels\Concerns\ReferentielModel;

final class Departement extends Model
{
    use HasUuid;
    use ReferentielModel;
    use SoftDeletes;

    protected $table = 'departements';

    /** @var array<int, string> */
    protected $fillable = ['faculte_id', 'code', 'nom', 'description', 'active', 'display_order'];

    /** @var array<string, string> */
    protected $casts = [
        'active'        => 'boolean',
        'display_order' => 'integer',
    ];

    /** @return BelongsTo<Faculte, $this> */
    public function faculte(): BelongsTo
    {
        return $this->belongsTo(Faculte::class);
    }

    /** @return HasMany<Section> */
    public function sections(): HasMany
    {
        return $this->hasMany(Section::class);
    }

    /** @return HasMany<Salle> */
    public function salles(): HasMany
    {
        return $this->hasMany(Salle::class);
    }

    /** @return array<string, list<string>> */
    public static function validationRules(): array
    {
        return [
            'faculte_id'    => ['nullable', 'uuid', 'exists:facultes,id'],
            'code'          => ['required', 'string', 'max:30'],
            'nom'           => ['required', 'string', 'max:191'],
            'description'   => ['nullable', 'string'],
            'active'        => ['sometimes', 'boolean'],
            'display_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
