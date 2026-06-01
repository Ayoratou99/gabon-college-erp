<?php

declare(strict_types=1);

namespace Modules\Concours\Models;

use App\Foundation\Concerns\HasUuid;
use App\Foundation\Exports\Concerns\HasExportableColumns;
use App\Foundation\Permissions\Contracts\Scopable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\AcademicStructure\Models\Section;
use Modules\Referentiels\Models\Nationalite;
use Modules\Referentiels\Models\SerieBac;
use Modules\UserManagement\Models\User;

/**
 * A candidate registered for a given concours session.
 *
 * Scopable: declares which columns map to which RBAC scope. Combined with
 * the foundation OwnCenterResolver / OwnSessionResolver, this lets
 * `chef-centre` users see only candidats of their centre, and `DE` see
 * only candidats of the active session.
 *
 * `matricule_public` is a short opaque token used in the public verification
 * URL — much friendlier than the full UUID and impossible to enumerate
 * (random alphanumeric, generated at registration).
 */
final class Candidat extends Model implements Scopable
{
    use HasExportableColumns;
    use HasUuid;
    use SoftDeletes;

    public const STATUS_NON    = 'non';
    public const STATUS_OUI    = 'oui';
    public const STATUS_VALID  = 'valid';
    public const STATUS_REJETE = 'rejete';
    public const STATUS_ADMIS  = 'admis';

    protected $table = 'candidats';

    /** @var array<int, string> */
    protected $fillable = [
        'concours_session_id', 'centre_id', 'user_id',
        'nom', 'prenom', 'date_naissance', 'lieu_naissance', 'sexe',
        'nationalite_id', 'email', 'telephone',
        'deja_bac', 'annee_bac', 'serie_bac_id', 'bac_libelle_libre',
        'etablissement_frequente',
        'section_premier_choix_id', 'section_second_choix_id', 'section_orientation_id',
        'photo_path', 'photo_disk',
        'statut', 'matricule_public', 'rang', 'moyenne',
        'valide_at', 'rejete_at', 'admis_at', 'admission_note',
        'is_test',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'date_naissance' => 'date',
        'annee_bac'      => 'integer',
        'deja_bac'       => 'boolean',
        'rang'           => 'integer',
        'moyenne'        => 'decimal:2',
        'valide_at'      => 'datetime',
        'rejete_at'      => 'datetime',
        'admis_at'       => 'datetime',
        'is_test'        => 'boolean',
        // `legacy_id` is stamped by LegacyCandidatImporter (via forceFill) and
        // read at runtime by the photo controller to probe the
        // imageprofilecupk folder — keeping it cast as int avoids string/int
        // comparisons when matching filenames like "{annee}user{legacy_id}".
        'legacy_id'      => 'integer',
    ];

    public function scopeColumnFor(string $scope): ?string
    {
        return match ($scope) {
            'own'         => 'user_id',
            'own_center'  => 'centre_id',
            'own_session' => 'concours_session_id',
            default       => null,
        };
    }

    // ----------------------------------------------------- relations

    /** @return BelongsTo<ConcoursSession, $this> */
    public function session(): BelongsTo
    {
        return $this->belongsTo(ConcoursSession::class, 'concours_session_id');
    }

    /** @return BelongsTo<Centre, $this> */
    public function centre(): BelongsTo
    {
        return $this->belongsTo(Centre::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Nationalite, $this> */
    public function nationalite(): BelongsTo
    {
        return $this->belongsTo(Nationalite::class);
    }

    /** @return BelongsTo<SerieBac, $this> */
    public function serieBac(): BelongsTo
    {
        return $this->belongsTo(SerieBac::class, 'serie_bac_id');
    }

    /** @return BelongsTo<Section, $this> */
    public function premierChoix(): BelongsTo
    {
        return $this->belongsTo(Section::class, 'section_premier_choix_id');
    }

    /** @return BelongsTo<Section, $this> */
    public function secondChoix(): BelongsTo
    {
        return $this->belongsTo(Section::class, 'section_second_choix_id');
    }

    /** @return BelongsTo<Section, $this> */
    public function sectionOrientation(): BelongsTo
    {
        return $this->belongsTo(Section::class, 'section_orientation_id');
    }

    /** @return HasMany<Note> */
    public function notes(): HasMany
    {
        return $this->hasMany(Note::class);
    }

    /** @return HasMany<CandidatDocument> */
    public function documents(): HasMany
    {
        return $this->hasMany(CandidatDocument::class);
    }

    /** @return HasMany<CandidatMotifRejet> */
    public function motifsRejet(): HasMany
    {
        return $this->hasMany(CandidatMotifRejet::class)->latest('decided_at');
    }

    /** @return HasMany<CandidatModification> */
    public function modifications(): HasMany
    {
        return $this->hasMany(CandidatModification::class)->latest('changed_at');
    }

    /** @return HasMany<Payment> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    // ----------------------------------------------------- helpers

    public function isPending(): bool   { return $this->statut === self::STATUS_NON; }
    public function isAccepted(): bool  { return $this->statut === self::STATUS_OUI; }
    public function isPaid(): bool      { return $this->statut === self::STATUS_VALID; }
    public function isRejected(): bool  { return $this->statut === self::STATUS_REJETE; }

    // ------------------------------------------------- status display labels
    //
    // The stored statut (non/oui/valid/rejete/admis) is a SYSTEM code and never
    // changes. These helpers exist ONLY so views — public and back-office —
    // can show something a human actually understands instead of the raw code.

    /** @return array<string, string> statut code → friendly French label */
    public static function statutLabels(): array
    {
        return [
            self::STATUS_NON    => 'En cours',
            self::STATUS_OUI    => 'En attente de paiement',
            self::STATUS_VALID  => 'Validé',
            self::STATUS_REJETE => 'Rejeté',
            self::STATUS_ADMIS  => 'Admis',
        ];
    }

    /** Friendly label for THIS candidat's statut (display only). */
    public function statutLabel(): string
    {
        return self::statutLabels()[$this->statut] ?? ucfirst((string) $this->statut);
    }

    /**
     * Display metadata for a status badge: friendly label + Bootstrap colour +
     * FontAwesome icon. Display only — never drives logic.
     *
     * @return array{label: string, css: string, icon: string}
     */
    public function statutBadge(): array
    {
        return match ($this->statut) {
            self::STATUS_NON    => ['label' => 'En cours',               'css' => 'secondary',         'icon' => 'fa-hourglass-half'],
            self::STATUS_OUI    => ['label' => 'En attente de paiement', 'css' => 'warning text-dark', 'icon' => 'fa-clock'],
            self::STATUS_VALID  => ['label' => 'Validé',                 'css' => 'success',           'icon' => 'fa-circle-check'],
            self::STATUS_REJETE => ['label' => 'Rejeté',                 'css' => 'danger',            'icon' => 'fa-circle-xmark'],
            self::STATUS_ADMIS  => ['label' => 'Admis',                  'css' => 'info',              'icon' => 'fa-trophy'],
            default             => ['label' => ucfirst((string) $this->statut), 'css' => 'secondary', 'icon' => 'fa-circle'],
        };
    }

    /** The prod QA "test candidate" (see config('concours.test')). */
    public function isTest(): bool      { return (bool) $this->is_test; }

    /** Configured QA test email (empty string when the backdoor is disabled). */
    public static function testEmail(): string
    {
        return (string) config('concours.test.email', '');
    }

    /** Whether $email is the configured QA test address (backdoor enabled + match). */
    public static function isTestEmail(?string $email): bool
    {
        $test = self::testEmail();

        return $test !== '' && $email !== null && mb_strtolower(trim($email)) === $test;
    }

    /**
     * Hide the prod test candidate from STAFF aggregations / lists unless the
     * viewer is super-admin. Public + candidate-self flows (registration,
     * payment, public dossier lookup) never call this, so the test candidate
     * can still complete the full register → accept → pay → validate journey.
     *
     * `qualifyColumn` keeps the clause correct when the query joins centres
     * (the dashboard does `candidats.is_test`, not an ambiguous `is_test`).
     *
     * @param  Builder<Candidat>  $query
     * @param  mixed  $viewer  the authenticated user (or null for guests/console)
     * @return Builder<Candidat>
     */
    public function scopeVisibleToStaff(Builder $query, mixed $viewer = null): Builder
    {
        if ($viewer instanceof User && $viewer->hasRole('super-admin')) {
            return $query;
        }

        return $query->where($this->qualifyColumn('is_test'), false);
    }

    // ----------------------------------------------------- exports

    /** @return list<array<string, mixed>> */
    public static function exportColumns(): array
    {
        return [
            ['header' => 'Matricule',     'accessor' => 'matricule_public'],
            ['header' => 'Nom',           'accessor' => 'nom'],
            ['header' => 'Prénom',        'accessor' => 'prenom'],
            ['header' => 'Sexe',          'accessor' => 'sexe',           'align' => 'center'],
            ['header' => 'Date naiss.',   'accessor' => 'date_naissance', 'format' => 'date'],
            ['header' => 'Email',         'accessor' => 'email'],
            ['header' => 'Téléphone',     'accessor' => 'telephone'],
            ['header' => 'Centre',        'accessor' => fn (Candidat $c): ?string => $c->centre?->nom],
            ['header' => 'Premier choix', 'accessor' => fn (Candidat $c): ?string => $c->premierChoix?->nom],
            ['header' => 'Statut',        'accessor' => 'statut',         'align' => 'center'],
            ['header' => 'Moyenne',       'accessor' => 'moyenne',        'format' => 'decimal', 'align' => 'right'],
            ['header' => 'Rang',          'accessor' => 'rang',           'format' => 'integer', 'align' => 'right'],
            ['header' => 'Orientation',   'accessor' => fn (Candidat $c): ?string => $c->sectionOrientation?->nom],
            ['header' => 'Inscrit le',    'accessor' => 'created_at',     'format' => 'datetime'],
        ];
    }

    /** @return list<string> */
    public static function exportRelations(): array
    {
        return ['centre:id,nom', 'premierChoix:id,nom', 'sectionOrientation:id,nom'];
    }
}
