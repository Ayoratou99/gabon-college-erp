<?php

declare(strict_types=1);

namespace Modules\Concours\Http\Controllers\Admin\Pages;

use App\Foundation\Http\DataTables\DataTablesQuery;
use App\Foundation\Permissions\PermissionChecker;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
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

        // Archived sessions are read-only — view drops the +Nouvelle épreuve
        // button + the row-level Trash buttons in data() below.
        $sessionEditable = $session?->isEditable() ?? false;
        $canManage       = $this->checker->can($request->user(), 'create:epreuves:*')
                        && $sessionEditable;

        return view('concours::admin.epreuves.index', [
            'session'         => $session,
            'sessionEditable' => $sessionEditable,
            'types'           => TypeEpreuve::query()->where('active', true)->ordered()->get(['id', 'libelle']),
            // An épreuve can only target sections OPEN to the concours.
            'sections'        => Section::query()->where('active', true)->where('ouvert_au_concours', true)
                                    ->ordered()->get(['id', 'nom', 'code']),
            'canManage'       => $canManage,
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        if (! $this->checker->can($request->user(), 'view:epreuves:*')) {
            abort(Response::HTTP_FORBIDDEN);
        }

        $session   = ConcoursSession::active();
        // Don't surface the Trash button when the session is archived — the
        // EpreuveController would 409 the click anyway, but the UI shouldn't
        // pretend the row is mutable.
        $canManage = $this->checker->can($request->user(), 'create:epreuves:*')
                  && ($session?->isEditable() ?? false);
        $notesUrl  = fn (string $id) => route('admin.pages.concours.notes.grid', $id);

        $query = Epreuve::query()
            ->when($session, fn ($q) => $q->where('concours_session_id', $session->id))
            ->with(['typeEpreuve:id,libelle', 'plannings', 'sections:id,code']);

        return DataTablesQuery::for($query)
            ->searchable(['code', 'libelle'])
            ->orderable([
                'code'         => 'code',
                'libelle'      => 'libelle',
                'coefficient'  => 'coefficient',
                'duree'        => 'duree_minutes',
                'ordre'        => 'ordre',
            ])
            ->transform(function (Epreuve $e) use ($canManage, $notesUrl): array {
                $actions = sprintf(
                    '<a href="%s" class="btn btn-sm btn-outline-primary me-1"><i class="fas fa-marker"></i> Notes</a>',
                    e($notesUrl($e->id)),
                );
                if ($canManage) {
                    $actions .= sprintf(
                        '<button data-delete="%s" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>',
                        e($e->id),
                    );
                }
                return [
                    'id'          => $e->id,
                    'code'        => '<code>' . e($e->code) . '</code>',
                    'libelle'     => e($e->libelle),
                    'type'        => e($e->typeEpreuve?->libelle ?? '—'),
                    'scope'       => $e->sections->isEmpty()
                        ? '<small class="text-muted">—</small>'
                        : $e->sections->map(fn ($s) => '<span class="badge bg-light text-dark border me-1">' . e($s->code) . '</span>')->implode(''),
                    'coefficient' => number_format((float) $e->coefficient, 2, ',', ''),
                    'duree'       => $e->duree_minutes . ' min',
                    'centres'     => $e->plannings->isEmpty()
                        ? '<span class="text-muted">Non planifiée</span>'
                        : $e->plannings->count() . ' centre(s)',
                    'actions'     => $actions,
                ];
            })
            ->respond($request);
    }
}
