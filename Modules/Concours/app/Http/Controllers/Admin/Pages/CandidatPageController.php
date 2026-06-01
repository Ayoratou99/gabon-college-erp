<?php

declare(strict_types=1);

namespace Modules\Concours\Http\Controllers\Admin\Pages;

use App\Foundation\Exports\ExportBuilder;
use App\Foundation\Http\DataTables\DataTablesQuery;
use App\Foundation\Permissions\PermissionChecker;
use App\Foundation\Permissions\ScopedQuery;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\AcademicStructure\Models\Section;
use Modules\Concours\Models\Candidat;
use Modules\Concours\Models\Centre;
use Modules\Concours\Models\ConcoursSession;
use Modules\Referentiels\Models\Nationalite;
use Modules\Referentiels\Models\SerieBac;
use Symfony\Component\HttpFoundation\Response;

/**
 * Server-rendered candidat list + detail + exports.
 *
 * The list and the export endpoint share the same query-building logic via
 * `buildIndexQuery()` so a filtered Excel/PDF export always mirrors what's
 * on screen.
 */
final class CandidatPageController extends Controller
{
    public function __construct(
        private readonly ScopedQuery $scoped,
        private readonly PermissionChecker $checker,
    ) {}

    public function index(Request $request): View
    {
        if (! $this->checker->can($request->user(), 'view:candidats:*')) {
            abort(Response::HTTP_FORBIDDEN);
        }

        $session = ConcoursSession::active();

        return view('concours::admin.candidats.index', [
            'session'   => $session,
            'centres'   => Centre::query()->where('active', true)->orderBy('nom')->get(['id', 'nom']),
            'sections'  => Section::query()->where('active', true)->orderBy('nom')->get(['id', 'nom', 'code']),
            'series'    => SerieBac::query()->where('active', true)->ordered()->get(['id', 'nom', 'code']),
            'statuses'  => Candidat::statutLabels(),
        ]);
    }

    /**
     * Server-side DataTables endpoint. Filter values come from the request body
     * under `filters.*` so the same UI can pre-filter status/centre.
     */
    public function data(Request $request): JsonResponse
    {
        if (! $this->checker->can($request->user(), 'view:candidats:*')) {
            abort(Response::HTTP_FORBIDDEN);
        }

        $session = ConcoursSession::active();
        $base    = $this->scopedBase($request, $session)
            ->with(['centre:id,nom', 'premierChoix:id,nom,code']);

        $showUrl = fn (string $id) => route('admin.pages.concours.candidats.show', $id);

        return DataTablesQuery::for($base)
            ->searchable(['nom', 'prenom', 'matricule_public', 'email', 'telephone'])
            ->orderable([
                'matricule_public' => 'matricule_public',
                'nom'              => 'nom',
                'date_naissance'   => 'date_naissance',
                'statut'           => 'statut',
                'created_at'       => 'created_at',
            ])
            ->filterUsing(fn (Builder $q, array $filters) => $this->applyFilters($q, $filters))
            ->transform(fn (Candidat $c): array => [
                'id'               => $c->id,
                'matricule_public' => $c->matricule_public,
                'nom'              => e($c->nom) . ' ' . e($c->prenom),
                'centre'           => $c->centre?->nom ?? '—',
                'premier_choix'    => $c->premierChoix?->nom ?? '—',
                'statut'           => sprintf(
                    '<span class="status-pill status-pill--%s">%s</span>',
                    e($c->statut),
                    e($c->statutLabel()),
                ),
                'created_at'       => $c->created_at?->format('d/m/Y H:i'),
                'actions'          => sprintf(
                    '<a href="%s" class="btn btn-sm btn-outline-primary">Voir</a>',
                    e($showUrl($c->id)),
                ),
            ])
            ->respond($request);
    }

    public function show(Request $request, Candidat $candidat): View
    {
        if (! $this->checker->can($request->user(), 'view:candidats:*', $candidat)) {
            abort(Response::HTTP_FORBIDDEN);
        }

        // The QA test candidate's dossier is visible to super-admin only.
        if ($candidat->isTest() && ! ($request->user()?->hasRole('super-admin') ?? false)) {
            abort(Response::HTTP_NOT_FOUND);
        }

        $candidat->load([
            // `date_ouverture_inscriptions` + `date_fermeture_inscriptions` are
            // required by ConcoursSession::isInscriptionOpen() — without them
            // Laravel 11+ strict mode throws MissingAttributeException when
            // we compute $sessionActive below.
            'session:id,code,libelle,date_concours,date_ouverture_inscriptions,date_fermeture_inscriptions',
            'centre',
            'nationalite:id,nom',
            'serieBac:id,nom',
            'premierChoix:id,nom,code',
            'secondChoix:id,nom,code',
            'sectionOrientation:id,nom,code',
            'documents.documentRequis:id,code,libelle',
            'documents.reviewedBy:id,nom,prenom',
            'motifsRejet.decidedBy:id,nom,prenom',
            'payments' => fn ($q) => $q->latest('created_at'),
            'modifications' => fn ($q) => $q->with('user:id,nom,prenom')->latest('changed_at')->limit(20),
        ]);

        $user = $request->user();

        // Build the "expected docs" view: every active documents_requis row
        // that applies to this candidat's section (universal + section-
        // specific for premier_choix), keyed by code. The view joins this
        // against the uploaded `documents` collection to flag which
        // expected pieces are still missing.
        $expectedDocs = \Modules\Referentiels\Models\DocumentRequis::query()
            ->where('active', true)
            ->ordered()
            ->forSection((string) $candidat->section_premier_choix_id)
            ->get(['id', 'code', 'libelle', 'obligatoire'])
            ->keyBy('code');

        // Session-state flag — drives the visibility of every state-changing
        // action in the view (per-doc review buttons, bulk-validate, the
        // global accept/reject card). Read-only flows (preview, photo,
        // fiche PDF) are unaffected so the dossier stays browseable for
        // closed sessions (legacy concours, post-results consultation).
        $sessionActive = $candidat->session?->isInscriptionOpen() ?? false;

        return view('concours::admin.candidats.show', [
            'candidat'      => $candidat,
            'expectedDocs'  => $expectedDocs,
            'canValidate'   => $this->checker->can($user, 'validate:candidats:*', $candidat),
            'canEdit'       => $this->checker->can($user, 'edit:candidats:*', $candidat)
                            || $this->checker->can($user, 'edit:candidats:own_center', $candidat),
            'sessionActive' => $sessionActive,
        ]);
    }

    /**
     * Server-rendered edit form. The actual PUT goes to the JSON API
     * (CandidatController::update) — this page only shows the form +
     * loads reference data (centres, sections, séries, nationalités).
     *
     *   GET /admin/concours/candidats/{candidat}/edit
     */
    public function edit(Request $request, Candidat $candidat): View
    {
        $user = $request->user();

        $canEditAll        = $this->checker->can($user, 'edit:candidats:*', $candidat);
        $canEditOwnCenter  = $this->checker->can($user, 'edit:candidats:own_center', $candidat);

        if (! $canEditAll && ! $canEditOwnCenter) {
            abort(Response::HTTP_FORBIDDEN);
        }

        $candidat->load([
            'session:id,code,libelle,date_concours',
            'centre:id,nom',
            'nationalite:id,nom',
            'serieBac:id,nom',
            'premierChoix:id,nom,code',
            'secondChoix:id,nom,code',
        ]);

        return view('concours::admin.candidats.edit', [
            'candidat'      => $candidat,
            // Only DG / DE / super-admin can reassign the candidat to a
            // different centre. Chef-centre gets a read-only field.
            'canChangeCentre' => $canEditAll,
            'centres'       => Centre::query()->where('active', true)->orderBy('nom')->get(['id', 'nom', 'ville', 'adresse']),
            'sections'      => \Modules\AcademicStructure\Models\Section::query()
                ->where('active', true)->orderBy('nom')
                ->get(['id', 'nom', 'code']),
            'series'        => SerieBac::query()->where('active', true)->ordered()->get(['id', 'nom', 'code']),
            'nationalites'  => Nationalite::query()->where('active', true)->orderBy('nom')->get(['id', 'nom']),
        ]);
    }

    /**
     * Single export endpoint reused by every format.
     *
     *   GET /admin/concours/candidats/export.{xlsx|csv|pdf}?statut=valid&centre_id=...
     */
    public function export(Request $request, string $format): Response
    {
        if (! $this->checker->can($request->user(), 'view:candidats:*')) {
            abort(Response::HTTP_FORBIDDEN);
        }

        $session = ConcoursSession::active();
        $query   = $this->buildIndexQuery($request, $session)->orderBy('nom')->orderBy('prenom');

        return ExportBuilder::for($query)
            ->columnsFromModel(Candidat::class)
            ->title('Candidats — ' . ($session?->libelle ?? 'session active'))
            ->meta(array_filter([
                'Session'  => $session?->code,
                'Statut'   => $request->string('statut')->toString() ?: 'tous',
                'Centre'   => $request->string('centre_id')->toString()
                    ? optional(Centre::query()->find($request->string('centre_id')->toString()))->nom
                    : 'tous',
                'Recherche'=> $request->string('search')->toString() ?: null,
            ]))
            ->filename('candidats-' . ($session?->code ?? 'session'))
            ->landscape()
            ->download($format);
    }

    /** Base query — RBAC scope + active session. Reused by exports + AJAX. */
    private function scopedBase(Request $request, ?ConcoursSession $session): Builder
    {
        $query = Candidat::query();
        $query = $this->scoped->apply($query, $request->user(), 'view', 'candidats')
            ->visibleToStaff($request->user());

        if ($session !== null) {
            $query->where('concours_session_id', $session->id);
        }
        return $query;
    }

    /** Used by the export endpoint; applies the GET-string filters via the
     *  same applier as the DataTables AJAX feed so the two stay in sync. */
    private function buildIndexQuery(Request $request, ?ConcoursSession $session): Builder
    {
        $query   = $this->scopedBase($request, $session);
        $filters = $request->only([
            'statut', 'centre_id', 'section_id', 'serie_bac_id',
            'deja_bac', 'sexe', 'admis_only', 'paye',
        ]);
        $this->applyFilters($query, array_filter($filters, static fn ($v): bool => $v !== null && $v !== ''));

        if ($search = $request->string('search')->toString()) {
            $query->where(function (Builder $q) use ($search): void {
                $q->where('nom', 'ilike', "%{$search}%")
                  ->orWhere('prenom', 'ilike', "%{$search}%")
                  ->orWhere('matricule_public', 'ilike', "%{$search}%")
                  ->orWhere('email', 'ilike', "%{$search}%");
            });
        }
        return $query;
    }

    /**
     * Single, authoritative filter applier. Used by both the AJAX list and
     * the export endpoint so a "filtered Excel" really matches what's on
     * screen.
     *
     * Supported keys:
     *   statut, centre_id, section_id (premier_choix), serie_bac_id,
     *   deja_bac ("oui" / "non"), sexe ("M" / "F"),
     *   admis_only (truthy → only candidats marked admis; becomes useful
     *               post-results-publication),
     *   paye      ("oui" → candidats whose dossier has at least one PAID
     *              Payment row; "attente" → accepted/oui statut with no
     *              PAID payment yet; "non" → never accepted, no payment
     *              applicable).
     *
     * @param  array<string, mixed>  $filters
     */
    private function applyFilters(Builder $q, array $filters): void
    {
        if (! empty($filters['statut'])) {
            $q->where('statut', $filters['statut']);
        }
        if (! empty($filters['centre_id'])) {
            $q->where('centre_id', $filters['centre_id']);
        }
        if (! empty($filters['section_id'])) {
            $q->where('section_premier_choix_id', $filters['section_id']);
        }
        if (! empty($filters['serie_bac_id'])) {
            $q->where('serie_bac_id', $filters['serie_bac_id']);
        }
        if (isset($filters['deja_bac']) && $filters['deja_bac'] !== '') {
            $q->where('deja_bac', $filters['deja_bac'] === 'oui' || $filters['deja_bac'] === '1' || $filters['deja_bac'] === true);
        }
        if (! empty($filters['sexe']) && in_array($filters['sexe'], ['M', 'F'], true)) {
            $q->where('sexe', $filters['sexe']);
        }
        if (! empty($filters['admis_only'])) {
            $q->where('statut', Candidat::STATUS_ADMIS);
        }
        // Payment-state filter — uses the payments relationship rather than
        // candidat.statut so a candidat who paid then was admis still
        // appears under "Payé". Three buckets: oui (paid), attente
        // (accepted but no paid payment yet), non (never accepted).
        if (! empty($filters['paye'])) {
            match ($filters['paye']) {
                'oui' => $q->whereHas('payments', fn (Builder $p) => $p->where('status', \Modules\Concours\Models\Payment::STATUS_PAID)),
                'attente' => $q->where('statut', Candidat::STATUS_OUI)
                    ->whereDoesntHave('payments', fn (Builder $p) => $p->where('status', \Modules\Concours\Models\Payment::STATUS_PAID)),
                'non' => $q->whereNotIn('statut', [Candidat::STATUS_OUI, Candidat::STATUS_VALID, Candidat::STATUS_ADMIS]),
                default => null,
            };
        }
    }
}
