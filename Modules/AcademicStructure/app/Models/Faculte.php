<?php

declare(strict_types=1);

namespace Modules\AcademicStructure\Models;

use App\Foundation\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Referentiels\Concerns\ReferentielModel;

final class Faculte extends Model
{
    use HasUuid;
    use ReferentielModel;
    use SoftDeletes;

    protected $table = 'facultes';

    /** @var array<int, string> */
    protected $fillable = ['code', 'nom', 'description', 'active', 'display_order'];

    /** @var array<string, string> */
    protected $casts = [
        'active'        => 'boolean',
        'display_order' => 'integer',
    ];

    /** @return HasMany<Departement> */
    public function departements(): HasMany
    {
        return $this->hasMany(Departement::class);
    }

    /** @return array<string, list<string>> */
    public static function validationRules(): array
    {
        return [
            'code'          => ['required', 'string', 'max:20'],
            'nom'           => ['required', 'string', 'max:191'],
            'description'   => ['nullable', 'string'],
            'active'        => ['sometimes', 'boolean'],
            'display_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
