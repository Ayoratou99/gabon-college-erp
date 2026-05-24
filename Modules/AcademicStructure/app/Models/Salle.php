<?php

declare(strict_types=1);

namespace Modules\AcademicStructure\Models;

use App\Foundation\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Referentiels\Concerns\ReferentielModel;

final class Salle extends Model
{
    use HasUuid;
    use ReferentielModel;
    use SoftDeletes;

    protected $table = 'salles';

    /** @var array<int, string> */
    protected $fillable = [
        'departement_id',
        'code', 'nom', 'capacite', 'type',
        'batiment', 'etage', 'accessible_pmr',
        'notes', 'active', 'display_order',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'capacite'         => 'integer',
        'accessible_pmr'   => 'boolean',
        'active'           => 'boolean',
        'display_order'    => 'integer',
    ];

    /** @return BelongsTo<Departement, $this> */
    public function departement(): BelongsTo
    {
        return $this->belongsTo(Departement::class);
    }

    /** @return array<string, list<string>> */
    public static function validationRules(): array
    {
        return [
            'departement_id'  => ['nullable', 'uuid', 'exists:departements,id'],
            'code'            => ['required', 'string', 'max:30'],
            'nom'             => ['required', 'string', 'max:191'],
            'capacite'        => ['required', 'integer', 'min:1', 'max:5000'],
            'type'            => ['required', 'in:salle,amphi,labo,td,examen'],
            'batiment'        => ['nullable', 'string', 'max:100'],
            'etage'           => ['nullable', 'string', 'max:20'],
            'accessible_pmr'  => ['sometimes', 'boolean'],
            'notes'           => ['nullable', 'string'],
            'active'          => ['sometimes', 'boolean'],
            'display_order'   => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
