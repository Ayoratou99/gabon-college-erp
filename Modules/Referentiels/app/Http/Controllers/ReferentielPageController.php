<?php

declare(strict_types=1);

namespace Modules\Referentiels\Http\Controllers;

use App\Foundation\Http\Concerns\RendersAdminTableRows;
use App\Foundation\Http\DataTables\DataTablesQuery;
use App\Foundation\Permissions\PermissionChecker;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Referentiels\Services\ReferentielRegistry;
use Symfony\Component\HttpFoundation\Response;

/**
 * Server-renders the shared admin page that wraps a DataTables grid + a
 * generic create/edit modal (Alpine `resourceCrud`).  All CRUD verbs still
 * hit the existing JSON API (`ReferentielController`).
 *
 * Per-slug UI metadata (page title, column list, field schema) lives in
 * `definitions()` so views stay agnostic.
 */
final class ReferentielPageController extends Controller
{
    use RendersAdminTableRows;

    public function __construct(
        private readonly ReferentielRegistry $registry,
        private readonly PermissionChecker $checker,
    ) {}

    public function index(Request $request, string $slug): View
    {
        $def = $this->definitionFor($slug);
        $this->assertCan($request, 'view', $slug);

        return view('referentiels::admin.index', [
            'slug'         => $slug,
            'definition'   => $def,
            'definitions'  => $this->definitions(),
            'canManage'    => $this->checker->can($request->user(), "edit:{$this->registry->resourceFor($slug)}:*"),
            'apiBase'      => url('/api/referentiels/' . $slug),
            'dataUrl'      => route('admin.referentiels.data', ['slug' => $slug]),
        ]);
    }

    public function data(Request $request, string $slug): JsonResponse
    {
        $this->assertCan($request, 'view', $slug);

        $def   = $this->definitionFor($slug);
        $model = $this->registry->modelFor($slug);

        return DataTablesQuery::for($model::query())
            ->searchable($def['searchable'])
            ->orderable($def['orderable'])
            ->transform(fn (Model $row): array => $this->renderRow($def['columns'], $row))
            ->respond($request);
    }

    /** @return array<string, array<string, mixed>> */
    private function definitions(): array
    {
        return [
            'provinces' => [
                'title' => 'Provinces',
                'icon'  => 'fas fa-map-marked-alt',
                'columns' => [
                    ['data' => 'code', 'label' => 'Code'],
                    ['data' => 'nom',  'label' => 'Nom'],
                    ['data' => 'display_order', 'label' => 'Ordre', 'className' => 'text-end'],
                    ['data' => 'active', 'label' => 'Actif', 'orderable' => false],
                ],
                'searchable' => ['code', 'nom'],
                'orderable'  => ['code' => 'code', 'nom' => 'nom', 'display_order' => 'display_order'],
                'fields' => [
                    ['name' => 'code',          'label' => 'Code',         'type' => 'text',    'required' => true],
                    ['name' => 'nom',           'label' => 'Nom',          'type' => 'text',    'required' => true],
                    ['name' => 'active',        'label' => 'Actif',        'type' => 'boolean'],
                    ['name' => 'display_order', 'label' => 'Ordre d’affichage', 'type' => 'integer'],
                ],
            ],
            'nationalites' => [
                'title' => 'Nationalités',
                'icon'  => 'fas fa-flag',
                'columns' => [
                    ['data' => 'code_iso', 'label' => 'Code ISO'],
                    ['data' => 'nom',      'label' => 'Nom'],
                    ['data' => 'display_order', 'label' => 'Ordre', 'className' => 'text-end'],
                    ['data' => 'active',   'label' => 'Actif', 'orderable' => false],
                ],
                'searchable' => ['code_iso', 'nom'],
                'orderable'  => ['code_iso' => 'code_iso', 'nom' => 'nom', 'display_order' => 'display_order'],
                'fields' => [
                    ['name' => 'code_iso',      'label' => 'Code ISO 3 lettres', 'type' => 'text', 'required' => true],
                    ['name' => 'nom',           'label' => 'Nom',                'type' => 'text', 'required' => true],
                    ['name' => 'active',        'label' => 'Actif',              'type' => 'boolean'],
                    ['name' => 'display_order', 'label' => 'Ordre',              'type' => 'integer'],
                ],
            ],
            'series-bac' => [
                'title' => 'Séries du Baccalauréat',
                'icon'  => 'fas fa-graduation-cap',
                'columns' => [
                    ['data' => 'code',        'label' => 'Code'],
                    ['data' => 'nom',         'label' => 'Nom'],
                    ['data' => 'description', 'label' => 'Description', 'orderable' => false],
                    ['data' => 'active',      'label' => 'Actif',       'orderable' => false],
                ],
                'searchable' => ['code', 'nom'],
                'orderable'  => ['code' => 'code', 'nom' => 'nom'],
                'fields' => [
                    ['name' => 'code',          'label' => 'Code',         'type' => 'text',     'required' => true],
                    ['name' => 'nom',           'label' => 'Nom',          'type' => 'text',     'required' => true],
                    ['name' => 'description',   'label' => 'Description',  'type' => 'textarea'],
                    ['name' => 'active',        'label' => 'Actif',        'type' => 'boolean'],
                    ['name' => 'display_order', 'label' => 'Ordre',        'type' => 'integer'],
                ],
            ],
            'documents' => [
                'title' => 'Documents requis',
                'icon'  => 'fas fa-file-shield',
                'columns' => [
                    ['data' => 'code',        'label' => 'Code'],
                    ['data' => 'libelle',     'label' => 'Libellé'],
                    ['data' => 'obligatoire', 'label' => 'Obligatoire', 'orderable' => false],
                    ['data' => 'taille_max_ko', 'label' => 'Taille max', 'className' => 'text-end'],
                    ['data' => 'active',      'label' => 'Actif',       'orderable' => false],
                ],
                'searchable' => ['code', 'libelle'],
                'orderable'  => ['code' => 'code', 'libelle' => 'libelle', 'taille_max_ko' => 'taille_max_ko'],
                'fields' => [
                    ['name' => 'code',          'label' => 'Code',                'type' => 'text',     'required' => true],
                    ['name' => 'libelle',       'label' => 'Libellé',             'type' => 'text',     'required' => true],
                    ['name' => 'description',   'label' => 'Description',         'type' => 'textarea'],
                    ['name' => 'obligatoire',   'label' => 'Obligatoire',         'type' => 'boolean'],
                    ['name' => 'taille_max_ko', 'label' => 'Taille max (Ko)',     'type' => 'integer'],
                    ['name' => 'active',        'label' => 'Actif',               'type' => 'boolean'],
                    ['name' => 'display_order', 'label' => 'Ordre',               'type' => 'integer'],
                ],
            ],
            'epreuves' => [
                'title' => 'Types d’épreuves',
                'icon'  => 'fas fa-pen-nib',
                'columns' => [
                    ['data' => 'code',     'label' => 'Code'],
                    ['data' => 'libelle',  'label' => 'Libellé'],
                    ['data' => 'modalite', 'label' => 'Modalité'],
                    ['data' => 'duree_minutes_defaut', 'label' => 'Durée (min)', 'className' => 'text-end'],
                    ['data' => 'coefficient_defaut',   'label' => 'Coef',         'className' => 'text-end'],
                    ['data' => 'active',   'label' => 'Actif', 'orderable' => false],
                ],
                'searchable' => ['code', 'libelle'],
                'orderable'  => [
                    'code'     => 'code',
                    'libelle'  => 'libelle',
                    'modalite' => 'modalite',
                    'duree_minutes_defaut' => 'duree_minutes_defaut',
                    'coefficient_defaut'   => 'coefficient_defaut',
                ],
                'fields' => [
                    ['name' => 'code',                 'label' => 'Code',          'type' => 'text',    'required' => true],
                    ['name' => 'libelle',              'label' => 'Libellé',       'type' => 'text',    'required' => true],
                    ['name' => 'description',          'label' => 'Description',   'type' => 'textarea'],
                    ['name' => 'modalite',             'label' => 'Modalité',      'type' => 'select',  'options' => [
                        'ecrit' => 'Écrit', 'oral' => 'Oral', 'pratique' => 'Pratique', 'mixte' => 'Mixte',
                    ]],
                    ['name' => 'duree_minutes_defaut', 'label' => 'Durée par défaut (min)', 'type' => 'integer'],
                    ['name' => 'coefficient_defaut',   'label' => 'Coefficient par défaut', 'type' => 'decimal', 'step' => '0.01'],
                    ['name' => 'active',               'label' => 'Actif',         'type' => 'boolean'],
                    ['name' => 'display_order',        'label' => 'Ordre',         'type' => 'integer'],
                ],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function definitionFor(string $slug): array
    {
        $defs = $this->definitions();
        if (! isset($defs[$slug])) {
            abort(Response::HTTP_NOT_FOUND, "Référentiel inconnu : {$slug}");
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
