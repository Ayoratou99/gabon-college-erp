<?php

declare(strict_types=1);

namespace Modules\Concours\Http\Controllers\Admin\Pages;

use App\Foundation\Permissions\PermissionChecker;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\AcademicStructure\Models\Cycle;
use Modules\AcademicStructure\Models\Section;
use Modules\Concours\Models\ConcoursSession;
use Modules\Concours\Models\Epreuve;
use Modules\Referentiels\Models\TypeEpreuve;
use Symfony\Component\HttpFoundation\Response;

final class EpreuvePageController extends Controller
{
    public function __construct(
        private readonly PermissionChecker $checker,
    ) {}

    public function index(Request $request): View
    {
        if (! $this->checker->can($request->user(), 'view:epreuves:*')) {
            abort(Response::HTTP_FORBIDDEN);
        }

        $session = ConcoursSession::active();

        $epreuves = Epreuve::query()
            ->when($session, fn ($q) => $q->where('concours_session_id', $session->id))
            ->with(['typeEpreuve:id,libelle', 'plannings'])
            ->orderBy('ordre')->orderBy('code')
            ->get();

        return view('concours::admin.epreuves.index', [
            'session'   => $session,
            'epreuves'  => $epreuves,
            'types'     => TypeEpreuve::query()->where('active', true)->ordered()->get(['id', 'libelle']),
            'cycles'    => Cycle::query()->where('active', true)->ordered()->get(['id', 'nom']),
            'sections'  => Section::query()->where('active', true)->ordered()->get(['id', 'nom', 'code']),
            'canManage' => $this->checker->can($request->user(), 'create:epreuves:*'),
        ]);
    }
}
