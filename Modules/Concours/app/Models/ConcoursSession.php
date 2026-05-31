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
            ->using(ConcoursSessionCentre::class)
            ->withPivot('id', 'lieu_concours', 'capacite_override', 'active')
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
     * A session is "archived" once the concours day is behind us (or the
     * lifecycle column is explicitly marked clos). Archived sessions are
     * read-only across the back-office: candidats, épreuves, plannings,
     * chef-centre assignments, centres-per-session etc. all refuse mutations.
     *
     * NB: `est_active` is just the "currently selected session" pointer —
     * it stays true on the legacy session until a new one is created so the
     * dashboard, exports and stats have a default scope. Archived != inactive.
     */
    public function isArchived(?Carbon $at = null): bool
    {
        if ($this->statut === 'clos') {
            return true;
        }
        $at ??= Carbon::now()->startOfDay();
        $jour = $this->date_concours;
        return $jour !== null && $at->greaterThan($jour);
    }

    /**
     * Inverse of isArchived — the helper most call sites really want.
     * "Editable" means we accept new candidats, decisions, épreuves,
     * planning entries etc. on this session.
     */
    public function isEditable(?Carbon $at = null): bool
    {
        return ! $this->isArchived($at);
    }

    /**
     * Human-readable status for the back-office: this drives the badge in
     * the sessions list + the "session sélectionnée" hint sprinkled across
     * admin pages. Order matters — once archived we stop caring about the
     * inscription window.
     *
     * @return array{key:string, label:string, css:string, icon:string}
     */
    public function lifecycleBadge(?Carbon $at = null): array
    {
        $at ??= Carbon::now()->startOfDay();
        if ($this->isArchived($at)) {
            return [
                'key' => 'archived', 'label' => 'Archivée',
                'css' => 'secondary', 'icon' => 'fa-box-archive',
            ];
        }
        if ($this->isInscriptionOpen($at)) {
            return [
                'key' => 'open', 'label' => 'Inscriptions ouvertes',
                'css' => 'success', 'icon' => 'fa-door-open',
            ];
        }
        if ($this->date_fermeture_inscriptions !== null
            && $at->greaterThan($this->date_fermeture_inscriptions)
        ) {
            return [
                'key' => 'inscriptions_closed', 'label' => 'Inscriptions closes',
                'css' => 'warning text-dark', 'icon' => 'fa-hourglass-end',
            ];
        }
        return [
            'key' => 'scheduled', 'label' => 'Planifiée',
            'css' => 'info', 'icon' => 'fa-calendar-day',
        ];
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
