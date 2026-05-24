<?php

declare(strict_types=1);

namespace Modules\Referentiels\Models;

use App\Foundation\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Referentiels\Concerns\ReferentielModel;

final class Nationalite extends Model
{
    use HasUuid;
    use ReferentielModel;
    use SoftDeletes;

    protected $table = 'nationalites';

    /** @var array<int, string> */
    protected $fillable = ['code_iso', 'nom', 'active', 'display_order'];

    /** @var array<string, string> */
    protected $casts = [
        'active'        => 'boolean',
        'display_order' => 'integer',
    ];

    /** @return array<string, list<string>> */
    public static function validationRules(): array
    {
        return [
            'code_iso'      => ['required', 'string', 'max:3'],
            'nom'           => ['required', 'string', 'max:100'],
            'active'        => ['sometimes', 'boolean'],
            'display_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
