<?php

declare(strict_types=1);

namespace App\Foundation\Http\Controllers;

use App\Foundation\Http\Contracts\ResourceRegistry;
use App\Foundation\Permissions\PermissionChecker;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

/**
 * Single REST surface for every registry-backed resource set.
 *
 *   GET    {prefix}/{slug}                  paginated admin list
 *   POST   {prefix}/{slug}                  create
 *   GET    {prefix}/{slug}/{id}             show
 *   PUT    {prefix}/{slug}/{id}             update (partial allowed)
 *   DELETE {prefix}/{slug}/{id}             soft-delete
 *   POST   {prefix}/{slug}/{id}/restore     restore
 *   GET    {prefix}/{slug}/public           active rows, public
 *
 * Subclasses just bind the registry. The validation rules and the RBAC
 * resource segment per slug come from the registry itself.
 *
 * Permission convention used:
 *   view:{resource}:*    | create:{resource}:* | edit:{resource}:* | delete:{resource}:*
 */
abstract class ResourceCrudController extends Controller
{
    public function __construct(
        protected readonly PermissionChecker $checker,
    ) {}

    abstract protected function registry(): ResourceRegistry;

    public function index(Request $request, string $slug): JsonResponse
    {
        $this->assertCan($request, 'view', $slug);

        $model = $this->registry()->modelFor($slug);
        $perPage = min(max((int) $request->integer('per_page', 50), 1), 200);

        $query = $model::query();
        $this->applySearch($query, $request, $model);

        if ($request->boolean('only_active')) {
            $query->where('active', true);
        }

        $query = method_exists($query, 'ordered') ? $query->ordered() : $query->orderBy('id');

        return response()->json($query->paginate($perPage));
    }

    public function publicIndex(string $slug): JsonResponse
    {
        $model = $this->registry()->modelFor($slug);
        $query = $model::query();
        if (method_exists($query, 'active')) {
            $query->active();
        }
        if (method_exists($query, 'ordered')) {
            $query->ordered();
        }
        return response()->json(['data' => $query->get()]);
    }

    public function show(Request $request, string $slug, string $id): JsonResponse
    {
        $this->assertCan($request, 'view', $slug);
        $row = $this->registry()->modelFor($slug)::query()->findOrFail($id);
        return response()->json($row);
    }

    public function store(Request $request, string $slug): JsonResponse
    {
        $this->assertCan($request, 'create', $slug);

        $data = Validator::validate($request->all(), $this->registry()->rulesFor($slug));
        $row = $this->registry()->modelFor($slug)::query()->create($data);

        return response()->json($row, Response::HTTP_CREATED);
    }

    public function update(Request $request, string $slug, string $id): JsonResponse
    {
        $this->assertCan($request, 'edit', $slug);

        $patchRules = $this->makePatchRules($this->registry()->rulesFor($slug));
        $data = Validator::validate($request->all(), $patchRules);

        $row = $this->registry()->modelFor($slug)::query()->findOrFail($id);
        $row->update($data);

        return response()->json($row);
    }

    public function destroy(Request $request, string $slug, string $id): Response
    {
        $this->assertCan($request, 'delete', $slug);

        $row = $this->registry()->modelFor($slug)::query()->findOrFail($id);
        $row->delete();

        return response()->noContent();
    }

    public function restore(Request $request, string $slug, string $id): JsonResponse
    {
        $this->assertCan($request, 'edit', $slug);

        $row = $this->registry()->modelFor($slug)::query()->withTrashed()->findOrFail($id);
        $row->restore();

        return response()->json($row);
    }

    // ---------------------------------------------------- helpers

    /** @param class-string $model */
    private function applySearch(Builder $query, Request $request, string $model): void
    {
        $search = $request->string('search')->toString();
        if ($search === '') {
            return;
        }

        $candidateCols = ['code', 'nom', 'libelle', 'code_iso'];
        $fillable = (new $model)->getFillable();
        $cols = array_values(array_intersect($candidateCols, $fillable));

        if ($cols === []) {
            return;
        }

        $query->where(function (Builder $q) use ($cols, $search): void {
            foreach ($cols as $col) {
                $q->orWhere($col, 'ilike', "%{$search}%");
            }
        });
    }

    /**
     * Strip "required" from each rule list so PUT can be partial.
     *
     * @param  array<string, list<string>>  $rules
     * @return array<string, list<string>>
     */
    private function makePatchRules(array $rules): array
    {
        return array_map(
            static fn (array $rule): array => array_values(array_filter(
                $rule,
                static fn (string $r): bool => $r !== 'required',
            )),
            $rules,
        );
    }

    private function assertCan(Request $request, string $action, string $slug): void
    {
        $resource = $this->registry()->resourceFor($slug);
        if (! $this->checker->can($request->user(), "{$action}:{$resource}:*")) {
            abort(Response::HTTP_FORBIDDEN, "Permission requise : {$action}:{$resource}:*");
        }
    }
}
