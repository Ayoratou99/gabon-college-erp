<?php

declare(strict_types=1);

namespace Modules\Concours\Http\Controllers\Admin;

use App\Foundation\Permissions\PermissionChecker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Concours\Http\Concerns\GuardsArchivedSession;
use Modules\Concours\Http\Requests\CreateEpreuveRequest;
use Modules\Concours\Models\ConcoursSession;
use Modules\Concours\Models\Epreuve;
use Symfony\Component\HttpFoundation\Response;

final class EpreuveController extends Controller
{
    use GuardsArchivedSession;

    public function __construct(
        private readonly PermissionChecker $checker,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->assertCan($request, 'view:epreuves:*');

        $sessionId = $request->string('session_id')->toString() ?: (string) ConcoursSession::active()?->id;

        $query = Epreuve::query()
            ->where('concours_session_id', $sessionId)
            ->with('typeEpreuve:id,code,libelle')
            ->orderBy('ordre')->orderBy('code');

        return response()->json($query->get());
    }

    public function show(Request $request, Epreuve $epreuve): JsonResponse
    {
        $this->assertCan($request, 'view:epreuves:*');
        return response()->json($epreuve->load(['typeEpreuve', 'plannings.salle']));
    }

    public function store(CreateEpreuveRequest $request): JsonResponse
    {
        $data = $request->validated();
        $this->assertSessionIdEditable($data['concours_session_id'] ?? null, 'une épreuve');

        $sections = $data['sections'] ?? [];
        unset($data['sections'], $data['scope_type'], $data['scope_id']);

        $epreuve = Epreuve::query()->create($data);
        $epreuve->sections()->sync($sections);

        return response()->json($epreuve->load('sections:id,code,nom'), Response::HTTP_CREATED);
    }

    public function update(CreateEpreuveRequest $request, Epreuve $epreuve): JsonResponse
    {
        $this->assertSessionEditable($epreuve->session, 'cette épreuve');

        $data = $request->validated();
        $sections = $data['sections'] ?? [];
        unset($data['sections'], $data['scope_type'], $data['scope_id']);

        $epreuve->update($data);
        $epreuve->sections()->sync($sections);

        return response()->json($epreuve->load('sections:id,code,nom'));
    }

    public function destroy(Request $request, Epreuve $epreuve): Response
    {
        $this->assertCan($request, 'delete:epreuves:*');
        $this->assertSessionEditable($epreuve->session, 'cette épreuve');
        $epreuve->delete();
        return response()->noContent();
    }

    private function assertCan(Request $request, string $perm): void
    {
        if (! $this->checker->can($request->user(), $perm)) {
            abort(Response::HTTP_FORBIDDEN, "Permission requise : {$perm}");
        }
    }
}
