<?php

declare(strict_types=1);

namespace Modules\Referentiels\Models;

use App\Foundation\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Referentiels\Concerns\ReferentielModel;

final class DocumentRequis extends Model
{
    use HasUuid;
    use ReferentielModel;
    use SoftDeletes;

    protected $table = 'documents_requis';

    /** @var array<int, string> */
    protected $fillable = [
        'code', 'libelle', 'description',
        'formats_acceptes', 'taille_max_ko',
        'obligatoire', 'active', 'display_order',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'formats_acceptes' => 'array',
        'taille_max_ko'    => 'integer',
        'obligatoire'      => 'boolean',
        'active'           => 'boolean',
        'display_order'    => 'integer',
    ];

    /** @return array<string, list<string>> */
    public static function validationRules(): array
    {
        return [
            'code'                => ['required', 'string', 'max:30'],
            'libelle'             => ['required', 'string', 'max:191'],
            'description'         => ['nullable', 'string'],
            'formats_acceptes'    => ['nullable', 'array'],
            'formats_acceptes.*'  => ['string', 'in:pdf,jpg,jpeg,png,webp,heic'],
            'taille_max_ko'       => ['sometimes', 'integer', 'min:1', 'max:51200'],
            'obligatoire'         => ['sometimes', 'boolean'],
            'active'              => ['sometimes', 'boolean'],
            'display_order'       => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
