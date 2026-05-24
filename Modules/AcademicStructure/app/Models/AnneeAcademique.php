<?php

declare(strict_types=1);

namespace Modules\AcademicStructure\Models;

use App\Foundation\Concerns\HasUuid;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Modules\Referentiels\Concerns\ReferentielModel;

/**
 * An academic year window. Exactly one row can have est_courante=true
 * (enforced by a Postgres partial unique index).
 *
 * Helper Self::current() returns the active année — used by Concours,
 * Scolarité, Examens, Evaluations to scope their queries.
 */
final class AnneeAcademique extends Model
{
    use HasUuid;
    use ReferentielModel;
    use SoftDeletes;

    protected $table = 'annees_academiques';

    /** @var array<int, string> */
    protected $fillable = [
        'code', 'date_debut', 'date_fin',
        'statut', 'est_courante', 'display_order',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'date_debut'    => 'date',
        'date_fin'      => 'date',
        'est_courante'  => 'boolean',
        'display_order' => 'integer',
    ];

    public function getLabelColumn(): string
    {
        return 'code';
    }

    public static function current(): ?self
    {
        return static::query()->where('est_courante', true)->first();
    }

    /**
     * Atomically switch the current année. Wrapped in a transaction so the
     * partial unique index never sees two TRUE rows at once.
     */
    public function markAsCurrent(): void
    {
        DB::transaction(function (): void {
            static::query()
                ->where('est_courante', true)
                ->whereKeyNot($this->getKey())
                ->update(['est_courante' => false]);

            $this->forceFill(['est_courante' => true, 'statut' => 'en_cours'])->save();
        });
    }

    public function isOngoing(?Carbon $at = null): bool
    {
        $at ??= Carbon::now();
        return $at->between($this->date_debut, $this->date_fin);
    }

    /** @return array<string, list<string>> */
    public static function validationRules(): array
    {
        return [
            'code'          => ['required', 'string', 'max:20', 'regex:/^\d{4}-\d{4}$/'],
            'date_debut'    => ['required', 'date'],
            'date_fin'      => ['required', 'date', 'after:date_debut'],
            'statut'        => ['sometimes', 'in:a_venir,en_cours,terminee'],
            'est_courante'  => ['sometimes', 'boolean'],
            'display_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
