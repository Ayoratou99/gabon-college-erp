<?php

declare(strict_types=1);

namespace Modules\Concours\Http\Controllers\Admin\Pages;

use App\Foundation\Permissions\PermissionChecker;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\AcademicStructure\Models\Section;
use Modules\Concours\Models\ConcoursSession;
use Modules\Concours\Models\ResultPublication;
use Symfony\Component\HttpFoundation\Response;

final class SelectionPageController extends Controller
{
    public function __construct(
        private readonly PermissionChecker $checker,
    ) {}

    public function wizard(Request $request): View
    {
        if (! $this->checker->can($request->user(), 'publish:results:*')) {
            abort(Response::HTTP_FORBIDDEN);
        }

        $session = ConcoursSession::active();
        $publication = $session ? ResultPublication::latestActiveFor($session->id) : null;

        $sections = Section::query()
            ->where('ouvert_au_concours', true)
            ->where('active', true)
            ->orderBy('nom')
            ->get(['id', 'code', 'nom', 'places_par_session']);

        return view('concours::admin.selection.wizard', [
            'session'     => $session,
            'sections'    => $sections,
            'publication' => $publication,
        ]);
    }
}
