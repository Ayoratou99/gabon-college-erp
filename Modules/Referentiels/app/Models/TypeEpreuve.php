<?php

declare(strict_types=1);

namespace Modules\Referentiels\Models;

use App\Foundation\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Referentiels\Concerns\ReferentielModel;

final class TypeEpreuve extends Model
{
    use HasUuid;
    use ReferentielModel;
    use SoftDeletes;

    protected $table = 'types_epreuves';

    /** @var array<int, string> */
    protected $fillable = [
        'code', 'libelle', 'description', 'modalite',
        'duree_minutes_defaut', 'coefficient_defaut',
        'active', 'display_order',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'duree_minutes_defaut' => 'integer',
        'coefficient_defaut'   => 'decimal:2',
        'active'               => 'boolean',
        'display_order'        => 'integer',
    ];

    /** @return array<string, list<string>> */
    public static function validationRules(): array
    {
        return [
            'code'                 => ['required', 'string', 'max:30'],
            'libelle'              => ['required', 'string', 'max:100'],
            'description'          => ['nullable', 'string'],
            'modalite'             => ['required', 'in:ecrit,oral,pratique,mixte'],
            'duree_minutes_defaut' => ['sometimes', 'integer', 'min:5', 'max:600'],
            'coefficient_defaut'   => ['sometimes', 'numeric', 'min:0', 'max:99.99'],
            'active'               => ['sometimes', 'boolean'],
            'display_order'        => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
