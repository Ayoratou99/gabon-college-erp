<?php

declare(strict_types=1);

namespace Modules\Concours\Models;

use App\Foundation\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Referentiels\Concerns\ReferentielModel;
use Modules\Referentiels\Models\Province;

final class Centre extends Model
{
    use HasUuid;
    use ReferentielModel;
    use SoftDeletes;

    protected $table = 'centres';

    /** @var array<int, string> */
    protected $fillable = [
        'province_id', 'code', 'nom', 'ville', 'adresse',
        'capacite_par_defaut', 'active', 'display_order',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'capacite_par_defaut' => 'integer',
        'active'              => 'boolean',
        'display_order'       => 'integer',
    ];

    /** @return BelongsTo<Province, $this> */
    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }

    /** @return BelongsToMany<ConcoursSession> */
    public function sessions(): BelongsToMany
    {
        return $this->belongsToMany(
            ConcoursSession::class,
            'concours_session_centres',
            'centre_id',
            'concours_session_id',
        )
            ->using(ConcoursSessionCentre::class)
            ->withPivot('id', 'lieu_concours', 'capacite_override', 'active')
            ->withTimestamps();
    }

    /** @return array<string, list<string>> */
    public static function validationRules(): array
    {
        return [
            'province_id'         => ['nullable', 'uuid', 'exists:provinces,id'],
            'code'                => ['required', 'string', 'max:30'],
            'nom'                 => ['required', 'string', 'max:100'],
            'ville'               => ['nullable', 'string', 'max:100'],
            'adresse'             => ['nullable', 'string'],
            'capacite_par_defaut' => ['sometimes', 'integer', 'min:1', 'max:100000'],
            'active'              => ['sometimes', 'boolean'],
            'display_order'       => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
