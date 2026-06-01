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

    /** @return HasMany<ResultPublication> */
    public function resultPublications(): HasMany
    {
        return $this->hasMany(ResultPublication::class, 'concours_session_id');
    }

    /**
     * Back-office "currently selected session" pointer (est_active). Admins move
     * it around to review an OLD session's data, reports, planning etc. — so it
     * must NOT drive anything the public sees. Use publicCurrent() for that.
     */
    public static function active(): ?self
    {
        return static::query()->where('est_active', true)->first();
    }

    /**
     * The session the PUBLIC interacts with for inscriptions — always the most
     * recently CREATED session, independent of the back-office est_active
     * pointer. When an admin creates a new concours it becomes the public one
     * immediately; pointing the back-office at an old session to inspect its
     * data never leaks to the home page / inscription tunnel.
     *
     * Callers still gate on isInscriptionOpen(), so a brand-new session whose
     * inscription window hasn't opened yet correctly shows "revenez plus tard".
     */
    public static function publicCurrent(): ?self
    {
        return static::query()->orderByDesc('created_at')->first();
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
     * True once an ACTIVE result publication (procès-verbal / admis list)
     * exists for this session — the primary "archived" signal. Publishing the
     * results (SelectionService::confirm, or the legacy PV seeder) freezes the
     * session; deactivating that publication (active=false, to re-run a
     * selection) reverses it.
     *
     * Eager-load `resultPublications` on list views to avoid an extra query.
     * Otherwise this runs one cheap exists() — an explicit query, so it's safe
     * even under preventLazyLoading (which only blocks magic relation access).
     */
    public function hasPublishedResults(): bool
    {
        if ($this->relationLoaded('resultPublications')) {
            return $this->resultPublications->contains(
                static fn (ResultPublication $p): bool => (bool) $p->active,
            );
        }

        return $this->resultPublications()->where('active', true)->exists();
    }

    /**
     * A session is "archived" (read-only across the back-office) once its
     * results are PUBLISHED — an active ResultPublication exists — or it is
     * explicitly closed (statut=clos).
     *
     * It is deliberately NOT archived just because the concours day has passed:
     * the window between the épreuves and the publication of the procès-verbal
     * is exactly when the DG enters marks and runs the selection, so the
     * session must stay editable then. (This replaced an earlier date-based
     * rule that wrongly froze brand-new sessions whose concours date sat in the
     * past.)
     *
     * Archived sessions refuse mutations on candidats, épreuves, plannings,
     * chef-centre assignments, centres-per-session etc.
     *
     * NB: `est_active` is just the "currently selected session" pointer — it is
     * orthogonal to archival. Archived != inactive.
     *
     * @param Carbon|null $at Accepted for call-site compatibility; archival no
     *                        longer depends on the clock.
     */
    public function isArchived(?Carbon $at = null): bool
    {
        return $this->statut === 'clos' || $this->hasPublishedResults();
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
     * Effective frais d'inscription for this session.
     *
     * The fee is OWNED by the session — set per-session via
     * `frais_inscription_override` in the back-office (Sessions → Modifier).
     * It is deliberately NOT read from Parametrage anymore: having it in two
     * places (session + settings) was ambiguous. When a session has no
     * explicit override we fall back to the deploy-time default
     * (config concours.payment.default_amount / env CONCOURS_DEFAULT_FEE).
     */
    public function fraisInscription(): int
    {
        if ($this->frais_inscription_override !== null) {
            return (int) $this->frais_inscription_override;
        }
        return (int) config('concours.payment.default_amount', 10300);
    }
}
