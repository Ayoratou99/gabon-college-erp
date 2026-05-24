<?php

declare(strict_types=1);

namespace Modules\Concours\Models;

use App\Foundation\Concerns\HasUuid;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Modules\AcademicStructure\Models\AnneeAcademique;
use Modules\Parametrage\Services\SettingsService;

final class ConcoursSession extends Model
{
    use HasUuid;
    use SoftDeletes;

    protected $table = 'concours_sessions';

    /** @var array<int, string> */
    protected $fillable = [
        'annee_academique_id', 'code', 'libelle',
        'date_ouverture_inscriptions', 'date_fermeture_inscriptions', 'date_concours',
        'frais_inscription_override', 'statut', 'est_active',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'date_ouverture_inscriptions' => 'date',
        'date_fermeture_inscriptions' => 'date',
        'date_concours'               => 'date',
        'frais_inscription_override'  => 'integer',
        'est_active'                  => 'boolean',
    ];

    /** @return BelongsTo<AnneeAcademique, $this> */
    public function anneeAcademique(): BelongsTo
    {
        return $this->belongsTo(AnneeAcademique::class);
    }

    /** @return BelongsToMany<Centre> */
    public function centres(): BelongsToMany
    {
        return $this->belongsToMany(Centre::class, 'concours_session_centres')
            ->withPivot('lieu_concours', 'capacite_override', 'active')
            ->withTimestamps();
    }

    /** @return HasMany<ChefCentreAssignment> */
    public function chefAssignments(): HasMany
    {
        return $this->hasMany(ChefCentreAssignment::class);
    }

    /** @return HasMany<Candidat> */
    public function candidats(): HasMany
    {
        return $this->hasMany(Candidat::class);
    }

    public static function active(): ?self
    {
        return static::query()->where('est_active', true)->first();
    }

    public function markAsActive(): void
    {
        DB::transaction(function (): void {
            static::query()
                ->where('est_active', true)
                ->whereKeyNot($this->getKey())
                ->update(['est_active' => false]);
            $this->forceFill(['est_active' => true])->save();
        });
    }

    public function isInscriptionOpen(?Carbon $at = null): bool
    {
        $at ??= Carbon::now()->startOfDay();
        return $at->between($this->date_ouverture_inscriptions, $this->date_fermeture_inscriptions);
    }

    /**
     * Effective frais: per-session override else Parametrage default.
     */
    public function fraisInscription(): int
    {
        if ($this->frais_inscription_override !== null) {
            return (int) $this->frais_inscription_override;
        }
        return (int) app(SettingsService::class)->get('concours.fee.amount', 10300);
    }
}
