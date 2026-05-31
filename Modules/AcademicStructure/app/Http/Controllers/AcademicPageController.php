<?php

declare(strict_types=1);

namespace Modules\AcademicStructure\Http\Controllers;

use App\Foundation\Http\Concerns\RendersAdminTableRows;
use App\Foundation\Http\DataTables\DataTablesQuery;
use App\Foundation\Permissions\PermissionChecker;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\AcademicStructure\Models\AnneeAcademique;
use Modules\AcademicStructure\Models\Cycle;
use Modules\AcademicStructure\Models\Departement;
use Modules\AcademicStructure\Models\Faculte;
use Modules\AcademicStructure\Models\Niveau;
use Modules\AcademicStructure\Models\Salle;
use Modules\AcademicStructure\Models\Section;
use Modules\AcademicStructure\Services\AcademicResourceRegistry;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sibling of ReferentielPageController for the AcademicStructure module —
 * facultés, départements, cycles, niveaux, sections, années, salles.
 */
final class AcademicPageController extends Controller
{
    use RendersAdminTableRows;

    public function __construct(
        private readonly AcademicResourceRegistry $registry,
        private readonly PermissionChecker $checker,
    ) {}

    public function index(Request $request, string $slug): View
    {
        $def = $this->definitionFor($slug);
        $this->assertCan($request, 'view', $slug);

        return view('academic::admin.index', [
            'slug'        => $slug,
            'definition'  => $def,
            'definitions' => $this->definitions(),
            'canManage'   => $this->checker->can($request->user(), "edit:{$this->registry->resourceFor($slug)}:*"),
            'apiBase'     => url('/api/academic/' . $slug),
            'dataUrl'     => route('admin.academic.data', ['slug' => $slug]),
        ]);
    }

    public function data(Request $request, string $slug): JsonResponse
    {
        $this->assertCan($request, 'view', $slug);

        $def   = $this->definitionFor($slug);
        $model = $this->registry->modelFor($slug);

        $query = $model::query();
        if (! empty($def['with'])) {
            $query->with($def['with']);
        }

        return DataTablesQuery::for($query)
            ->searchable($def['searchable'])
            ->orderable($def['orderable'])
            ->transform(fn (Model $row): array => $this->renderRow($def['columns'], $row))
            ->respond($request);
    }

    /** @return array<string, array<string, mixed>> */
    private function definitions(): array
    {
        $cycleOptions      = fn () => Cycle::query()->where('active', true)->ordered()->pluck('nom', 'id')->all();
        $faculteOptions    = fn () => Faculte::query()->where('active', true)->ordered()->pluck('nom', 'id')->all();
        $deptOptions       = fn () => Departement::query()->where('active', true)->ordered()->pluck('nom', 'id')->all();

        return [
            'facultes' => [
                'title' => 'Facultés',
                'icon'  => 'fas fa-university',
                'with'  => [],
                'columns' => [
                    ['data' => 'code',          'label' => 'Code'],
                    ['data' => 'nom',           'label' => 'Nom'],
                    ['data' => 'display_order', 'label' => 'Ordre', 'className' => 'text-end'],
                    ['data' => 'active',        'label' => 'Actif', 'orderable' => false],
                ],
                'searchable' => ['code', 'nom'],
                'orderable'  => ['code' => 'code', 'nom' => 'nom', 'display_order' => 'display_order'],
                'fields' => [
                    ['name' => 'code',          'label' => 'Code',  'type' => 'text', 'required' => true],
                    ['name' => 'nom',           'label' => 'Nom',   'type' => 'text', 'required' => true],
                    ['name' => 'description',   'label' => 'Description', 'type' => 'textarea'],
                    ['name' => 'active',        'label' => 'Actif',       'type' => 'boolean'],
                    ['name' => 'display_order', 'label' => 'Ordre',       'type' => 'integer'],
                ],
            ],
            'departements' => [
                'title' => 'Départements',
                'icon'  => 'fas fa-sitemap',
                'with'  => ['faculte:id,nom'],
                'columns' => [
                    ['data' => 'code',    'label' => 'Code'],
                    ['data' => 'nom',     'label' => 'Nom'],
                    ['data' => 'faculte', 'label' => 'Faculté', 'orderable' => false,
                     'render' => fn (Departement $r) => e($r->faculte?->nom ?? '—')],
                    ['data' => 'active',  'label' => 'Actif',   'orderable' => false],
                ],
                'searchable' => ['code', 'nom'],
                'orderable'  => ['code' => 'code', 'nom' => 'nom'],
                'fields' => [
                    ['name' => 'faculte_id',    'label' => 'Faculté', 'type' => 'select', 'options' => $faculteOptions()],
                    ['name' => 'code',          'label' => 'Code',    'type' => 'text', 'required' => true],
                    ['name' => 'nom',           'label' => 'Nom',     'type' => 'text', 'required' => true],
                    ['name' => 'description',   'label' => 'Description', 'type' => 'textarea'],
                    ['name' => 'active',        'label' => 'Actif',       'type' => 'boolean'],
                    ['name' => 'display_order', 'label' => 'Ordre',       'type' => 'integer'],
                ],
            ],
            'cycles' => [
                'title' => 'Cycles',
                'icon'  => 'fas fa-layer-group',
                'with'  => [],
                'columns' => [
                    ['data' => 'code',         'label' => 'Code'],
                    ['data' => 'nom',          'label' => 'Nom'],
                    ['data' => 'duree_annees', 'label' => 'Durée (années)', 'className' => 'text-end'],
                    ['data' => 'active',       'label' => 'Actif', 'orderable' => false],
                ],
                'searchable' => ['code', 'nom'],
                'orderable'  => ['code' => 'code', 'nom' => 'nom', 'duree_annees' => 'duree_annees'],
                'fields' => [
                    ['name' => 'code',          'label' => 'Code',   'type' => 'text',    'required' => true],
                    ['name' => 'nom',           'label' => 'Nom',    'type' => 'text',    'required' => true],
                    ['name' => 'description',   'label' => 'Description',    'type' => 'textarea'],
                    ['name' => 'duree_annees',  'label' => 'Durée (années)', 'type' => 'integer', 'required' => true],
                    ['name' => 'active',        'label' => 'Actif',          'type' => 'boolean'],
                    ['name' => 'display_order', 'label' => 'Ordre',          'type' => 'integer'],
                ],
            ],
            'niveaux' => [
                'title' => 'Niveaux',
                'icon'  => 'fas fa-stairs',
                'with'  => ['cycle:id,nom'],
                'columns' => [
                    ['data' => 'code',    'label' => 'Code'],
                    ['data' => 'libelle', 'label' => 'Libellé'],
                    ['data' => 'cycle',   'label' => 'Cycle', 'orderable' => false,
                     'render' => fn (Niveau $r) => e($r->cycle?->nom ?? '—')],
                    ['data' => 'ordre',   'label' => 'Ordre', 'className' => 'text-end'],
                    ['data' => 'est_niveau_entree', 'label' => 'Niveau d’entrée', 'orderable' => false],
                    ['data' => 'active',  'label' => 'Actif', 'orderable' => false],
                ],
                'searchable' => ['code', 'libelle'],
                'orderable'  => ['code' => 'code', 'libelle' => 'libelle', 'ordre' => 'ordre'],
                'fields' => [
                    ['name' => 'cycle_id',          'label' => 'Cycle',   'type' => 'select', 'options' => $cycleOptions(), 'required' => true],
                    ['name' => 'code',              'label' => 'Code',    'type' => 'text',    'required' => true],
                    ['name' => 'libelle',           'label' => 'Libellé', 'type' => 'text',    'required' => true],
                    ['name' => 'ordre',             'label' => 'Ordre dans le cycle', 'type' => 'integer', 'required' => true],
                    ['name' => 'est_niveau_entree', 'label' => 'Niveau d’entrée concours', 'type' => 'boolean'],
                    ['name' => 'active',            'label' => 'Actif',   'type' => 'boolean'],
                    ['name' => 'display_order',     'label' => 'Ordre',   'type' => 'integer'],
                ],
            ],
            'sections' => [
                'title' => 'Sections / Formations',
                'icon'  => 'fas fa-diagram-project',
                'with'  => ['cycle:id,nom', 'departement:id,nom'],
                'columns' => [
                    ['data' => 'code',        'label' => 'Code'],
                    ['data' => 'nom',         'label' => 'Nom'],
                    ['data' => 'cycle',       'label' => 'Cycle', 'orderable' => false,
                     'render' => fn (Section $r) => e($r->cycle?->nom ?? '—')],
                    ['data' => 'departement', 'label' => 'Département', 'orderable' => false,
                     'render' => fn (Section $r) => e($r->departement?->nom ?? '—')],
                    ['data' => 'places_par_session', 'label' => 'Places', 'className' => 'text-end'],
                    ['data' => 'ouvert_au_concours', 'label' => 'Ouvert au concours', 'orderable' => false],
                    ['data' => 'active',      'label' => 'Actif', 'orderable' => false],
                ],
                'searchable' => ['code', 'nom'],
                'orderable'  => ['code' => 'code', 'nom' => 'nom', 'places_par_session' => 'places_par_session'],
                'fields' => [
                    ['name' => 'cycle_id',           'label' => 'Cycle',       'type' => 'select', 'options' => $cycleOptions(), 'required' => true],
                    ['name' => 'departement_id',     'label' => 'Département', 'type' => 'select', 'options' => $deptOptions()],
                    ['name' => 'code',               'label' => 'Code',        'type' => 'text', 'required' => true],
                    ['name' => 'nom',                'label' => 'Nom',         'type' => 'text', 'required' => true],
                    ['name' => 'description',        'label' => 'Description', 'type' => 'textarea'],
                    ['name' => 'image_url',          'label' => 'Image (URL — affichée sur la page Nos formations)', 'type' => 'image_url'],
                    ['name' => 'places_par_session', 'label' => 'Places par session', 'type' => 'integer'],
                    // Off by default — admin must explicitly opt-in a section
                    // to appear in the public registration form.
                    ['name' => 'ouvert_au_concours', 'label' => 'Ouverte au concours d\'entrée', 'type' => 'boolean', 'default' => false],
                    ['name' => 'active',             'label' => 'Actif',       'type' => 'boolean', 'default' => true],
                    ['name' => 'display_order',      'label' => 'Ordre',       'type' => 'integer'],
                ],
            ],
            'annees-academiques' => [
                'title' => 'Années académiques',
                'icon'  => 'fas fa-calendar-days',
                'with'  => [],
                'columns' => [
                    ['data' => 'code',         'label' => 'Code'],
                    ['data' => 'date_debut',   'label' => 'Début',
                     'render' => fn (AnneeAcademique $r) => $r->date_debut?->format('d/m/Y') ?? '—'],
                    ['data' => 'date_fin',     'label' => 'Fin',
                     'render' => fn (AnneeAcademique $r) => $r->date_fin?->format('d/m/Y') ?? '—'],
                    ['data' => 'statut',       'label' => 'Statut'],
                    ['data' => 'est_courante', 'label' => 'Courante', 'orderable' => false],
                ],
                'searchable' => ['code'],
                'orderable'  => ['code' => 'code', 'statut' => 'statut'],
                'fields' => [
                    ['name' => 'code',          'label' => 'Code (ex. 2026-2027)', 'type' => 'text', 'required' => true],
                    ['name' => 'date_debut',    'label' => 'Date de début', 'type' => 'date',  'required' => true],
                    ['name' => 'date_fin',      'label' => 'Date de fin',   'type' => 'date',  'required' => true],
                    ['name' => 'statut',        'label' => 'Statut',        'type' => 'select', 'options' => [
                        'a_venir' => 'À venir', 'en_cours' => 'En cours', 'terminee' => 'Terminée',
                    ]],
                    ['name' => 'est_courante',  'label' => 'Année courante', 'type' => 'boolean'],
                    ['name' => 'display_order', 'label' => 'Ordre',          'type' => 'integer'],
                ],
            ],
            'salles' => [
                'title' => 'Salles',
                'icon'  => 'fas fa-chalkboard',
                'with'  => ['departement:id,nom'],
                'columns' => [
                    ['data' => 'code',           'label' => 'Code'],
                    ['data' => 'nom',            'label' => 'Nom'],
                    ['data' => 'type',           'label' => 'Type'],
                    ['data' => 'capacite',       'label' => 'Capacité', 'className' => 'text-end'],
                    ['data' => 'departement',    'label' => 'Département', 'orderable' => false,
                     'render' => fn (Salle $r) => e($r->departement?->nom ?? '—')],
                    ['data' => 'accessible_pmr', 'label' => 'PMR', 'orderable' => false],
                    ['data' => 'active',         'label' => 'Actif', 'orderable' => false],
                ],
                'searchable' => ['code', 'nom'],
                'orderable'  => ['code' => 'code', 'nom' => 'nom', 'type' => 'type', 'capacite' => 'capacite'],
                'fields' => [
                    ['name' => 'departement_id', 'label' => 'Département', 'type' => 'select', 'options' => $deptOptions()],
                    ['name' => 'code',           'label' => 'Code',        'type' => 'text', 'required' => true],
                    ['name' => 'nom',            'label' => 'Nom',         'type' => 'text', 'required' => true],
                    ['name' => 'capacite',       'label' => 'Capacité',    'type' => 'integer', 'required' => true],
                    ['name' => 'type',           'label' => 'Type',        'type' => 'select', 'options' => [
                        'salle' => 'Salle', 'amphi' => 'Amphi', 'labo' => 'Labo', 'td' => 'TD', 'examen' => 'Examen',
                    ], 'required' => true],
                    ['name' => 'batiment',       'label' => 'Bâtiment',    'type' => 'text'],
                    ['name' => 'etage',          'label' => 'Étage',       'type' => 'text'],
                    ['name' => 'accessible_pmr', 'label' => 'Accessible PMR', 'type' => 'boolean'],
                    ['name' => 'notes',          'label' => 'Notes',       'type' => 'textarea'],
                    ['name' => 'active',         'label' => 'Actif',       'type' => 'boolean'],
                    ['name' => 'display_order',  'label' => 'Ordre',       'type' => 'integer'],
                ],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function definitionFor(string $slug): array
    {
        $defs = $this->definitions();
        if (! isset($defs[$slug])) {
            abort(Response::HTTP_NOT_FOUND, "Ressource académique inconnue : {$slug}");
        }
        return $defs[$slug];
    }

    private function assertCan(Request $request, string $action, string $slug): void
    {
        $resource = $this->registry->resourceFor($slug);
        if (! $this->checker->can($request->user(), "{$action}:{$resource}:*")) {
            abort(Response::HTTP_FORBIDDEN);
        }
    }
}
