<?php

declare(strict_types=1);

namespace Modules\Concours\Http\Controllers\Admin;

use App\Foundation\Permissions\PermissionChecker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Concours\Exceptions\SelectionAlreadyPublishedException;
use Modules\Concours\Http\Requests\ConfirmSelectionRequest;
use Modules\Concours\Models\ResultPublication;
use Modules\Concours\Services\SelectionService;
use Symfony\Component\HttpFoundation\Response;

/**
 * Suggest → review → confirm flow:
 *
 *   GET  /api/admin/concours/selection/suggest?session_id=...
 *        → DG/DE sees a per-section top-N ranking.
 *   POST /api/admin/concours/selection/confirm
 *        → freezes the admis list + creates User accounts + publishes.
 *   GET  /api/admin/concours/selection/publication?session_id=...
 *        → returns the current active publication (if any).
 */
final class SelectionController extends Controller
{
    public function __construct(
        private readonly SelectionService $selection,
        private readonly PermissionChecker $checker,
    ) {}

    public function suggest(Request $request): JsonResponse
    {
        if (! $this->checker->can($request->user(), 'publish:results:*')) {
            abort(403);
        }
        $sessionId = (string) $request->string('concours_session_id')->toString();
        return response()->json($this->selection->suggest($sessionId));
    }

    public function confirm(ConfirmSelectionRequest $request): JsonResponse
    {
        // Store the optional PV (procès-verbal) PDF on the public disk so it can
        // be linked from the results page. Path/disk are recorded on the
        // ResultPublication via the DTO.
        $pvPath = null;
        $pvDisk = null;
        if ($request->hasFile('pv')) {
            $pvDisk = 'public';
            $pvPath = $request->file('pv')->store('result-publications', $pvDisk);
        }

        try {
            $publication = $this->selection->confirm($request->toDto(
                userId:       (string) $request->user()->getAuthIdentifier(),
                fichierPath:  $pvPath,
                fichierDisk:  $pvDisk,
            ));
        } catch (SelectionAlreadyPublishedException $e) {
            return response()->json(['error' => $e->getMessage()], Response::HTTP_CONFLICT);
        }
        return response()->json($publication, Response::HTTP_CREATED);
    }

    public function publication(Request $request): JsonResponse
    {
        $sessionId = (string) $request->string('concours_session_id')->toString();
        return response()->json(
            ResultPublication::latestActiveFor($sessionId)
        );
    }
}
