<?php

declare(strict_types=1);

namespace Modules\AcademicStructure\Models;

use App\Foundation\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Referentiels\Concerns\ReferentielModel;

final class Niveau extends Model
{
    use HasUuid;
    use ReferentielModel;
    use SoftDeletes;

    protected $table = 'niveaux';

    /** @var array<int, string> */
    protected $fillable = [
        'cycle_id', 'code', 'libelle', 'ordre',
        'est_niveau_entree', 'active', 'display_order',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'ordre'              => 'integer',
        'est_niveau_entree'  => 'boolean',
        'active'             => 'boolean',
        'display_order'      => 'integer',
    ];

    public function getLabelColumn(): string
    {
        return 'libelle';
    }

    /** @return BelongsTo<Cycle, $this> */
    public function cycle(): BelongsTo
    {
        return $this->belongsTo(Cycle::class);
    }

    /** @return array<string, list<string>> */
    public static function validationRules(): array
    {
        return [
            'cycle_id'           => ['required', 'uuid', 'exists:cycles,id'],
            'code'               => ['required', 'string', 'max:20'],
            'libelle'            => ['required', 'string', 'max:100'],
            'ordre'              => ['required', 'integer', 'min:1', 'max:10'],
            'est_niveau_entree'  => ['sometimes', 'boolean'],
            'active'             => ['sometimes', 'boolean'],
            'display_order'      => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
