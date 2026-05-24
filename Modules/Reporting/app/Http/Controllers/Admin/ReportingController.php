<?php

declare(strict_types=1);

namespace Modules\Reporting\Http\Controllers\Admin;

use App\Foundation\Permissions\PermissionChecker;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Concours\Models\ConcoursSession;
use Modules\Reporting\Services\StatisticsService;
use Symfony\Component\HttpFoundation\Response;

/**
 * Server-renders the dashboard shell (KPI cards + chart canvases) and
 * exposes 6 JSON endpoints the Alpine component fetches in parallel on
 * mount. Every JSON endpoint applies the same RBAC scope so chef-centre
 * users only ever see their centre's slice.
 */
final class ReportingController extends Controller
{
    public function __construct(
        private readonly StatisticsService $stats,
        private readonly PermissionChecker $checker,
    ) {}

    public function dashboard(Request $request): View
    {
        $this->assertView($request);
        $session = ConcoursSession::active();

        return view('reporting::admin.dashboard', [
            'session'  => $session,
            'summary'  => $this->stats->summary($session, $request->user()),
            'payments' => $this->stats->paymentsSummary($session),
        ]);
    }

    public function apiByStatus(Request $request): JsonResponse
    {
        $this->assertView($request);
        return response()->json($this->stats->byStatus(ConcoursSession::active(), $request->user()));
    }

    public function apiByCentre(Request $request): JsonResponse
    {
        $this->assertView($request);
        return response()->json($this->stats->byCentre(ConcoursSession::active(), $request->user()));
    }

    public function apiBySection(Request $request): JsonResponse
    {
        $this->assertView($request);
        return response()->json($this->stats->bySection(ConcoursSession::active(), $request->user()));
    }

    public function apiBySeriesBac(Request $request): JsonResponse
    {
        $this->assertView($request);
        return response()->json($this->stats->bySeriesBac(ConcoursSession::active(), $request->user()));
    }

    public function apiBySex(Request $request): JsonResponse
    {
        $this->assertView($request);
        return response()->json($this->stats->bySex(ConcoursSession::active(), $request->user()));
    }

    public function apiTimeline(Request $request): JsonResponse
    {
        $this->assertView($request);
        $days = min(max((int) $request->integer('days', 30), 7), 180);
        return response()->json($this->stats->registrationsTimeline($days, ConcoursSession::active(), $request->user()));
    }

    private function assertView(Request $request): void
    {
        $can = $this->checker->can($request->user(), 'view:reporting:*')
            || $this->checker->can($request->user(), 'view:reporting:own_center');
        if (! $can) {
            abort(Response::HTTP_FORBIDDEN);
        }
    }
}
