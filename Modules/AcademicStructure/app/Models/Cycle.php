<?php

declare(strict_types=1);

namespace Modules\AcademicStructure\Models;

use App\Foundation\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Referentiels\Concerns\ReferentielModel;

final class Cycle extends Model
{
    use HasUuid;
    use ReferentielModel;
    use SoftDeletes;

    protected $table = 'cycles';

    /** @var array<int, string> */
    protected $fillable = ['code', 'nom', 'description', 'duree_annees', 'active', 'display_order'];

    /** @var array<string, string> */
    protected $casts = [
        'duree_annees'  => 'integer',
        'active'        => 'boolean',
        'display_order' => 'integer',
    ];

    /** @return HasMany<Niveau> */
    public function niveaux(): HasMany
    {
        return $this->hasMany(Niveau::class)->orderBy('ordre');
    }

    /** @return HasMany<Section> */
    public function sections(): HasMany
    {
        return $this->hasMany(Section::class);
    }

    public function niveauEntree(): ?Niveau
    {
        return $this->niveaux()->where('est_niveau_entree', true)->first()
            ?? $this->niveaux()->orderBy('ordre')->first();
    }

    /** @return array<string, list<string>> */
    public static function validationRules(): array
    {
        return [
            'code'          => ['required', 'string', 'max:20'],
            'nom'           => ['required', 'string', 'max:100'],
            'description'   => ['nullable', 'string'],
            'duree_annees'  => ['required', 'integer', 'min:1', 'max:10'],
            'active'        => ['sometimes', 'boolean'],
            'display_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
