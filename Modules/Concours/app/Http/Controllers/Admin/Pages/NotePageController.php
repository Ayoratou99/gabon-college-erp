<?php

declare(strict_types=1);

namespace Modules\Concours\Http\Controllers\Admin\Pages;

use App\Foundation\Permissions\PermissionChecker;
use App\Foundation\Permissions\ScopedQuery;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\AcademicStructure\Models\Section;
use Modules\Concours\Models\Candidat;
use Modules\Concours\Models\Centre;
use Modules\Concours\Models\ConcoursSession;
use Modules\Concours\Models\Epreuve;
use Modules\Concours\Models\Note;
use Symfony\Component\HttpFoundation\Response;

/**
 *   GET /admin/concours/notes              → épreuve picker
 *   GET /admin/concours/notes/{epreuve}    → editable grid for that épreuve
 */
final class NotePageController extends Controller
{
    public function __construct(
        private readonly ScopedQuery $scoped,
        private readonly PermissionChecker $checker,
    ) {}

    public function picker(Request $request): View
    {
        if (! $this->canAccessNotes($request)) {
            abort(Response::HTTP_FORBIDDEN);
        }

        $session = ConcoursSession::active();
        $epreuves = Epreuve::query()
            ->when($session, fn ($q) => $q->where('concours_session_id', $session->id))
            ->where('active', true)
            ->with('typeEpreuve:id,libelle')
            ->orderBy('ordre')->orderBy('code')
            ->get();

        return view('concours::admin.notes.picker', compact('session', 'epreuves'));
    }

    public function grid(Request $request, Epreuve $epreuve): View
    {
        if (! $this->canAccessNotes($request)) {
            abort(Response::HTTP_FORBIDDEN);
        }

        // Chef-centre has VIEW-only access (view:notes:own_center); only holders
        // of an enter:notes permission get the editable grid.
        $canEnter = $this->checker->can($request->user(), 'enter:notes:*')
            || $this->checker->can($request->user(), 'enter:notes:own_center');

        // Base candidat set: eligible for this épreuve, scoped to what the user
        // may see (chef-centre → own centre). Only candidats who actually sit
        // the exam appear — i.e. those who PAID (statut=valid), plus admis
        // (already promoted) so they still show after results are published.
        // This mirrors MoyenneCalculatorService exactly, so notes ↔ moyenne ↔
        // selection all operate on the same population. Rebuilt twice: once to
        // derive the filter options, once with the active filters applied.
        $scopedBase = fn (): Builder => $this->scoped
            ->apply($epreuve->eligibleCandidatsQuery(), $request->user(), 'view', 'candidats')
            ->whereIn('statut', [Candidat::STATUS_VALID, Candidat::STATUS_ADMIS]);

        // Dropdown options come from the *visible* set, so we never offer a
        // centre/section that has no candidat behind it.
        $visible = $scopedBase()->get(['centre_id', 'section_premier_choix_id']);
        $centreOptions = Centre::query()
            ->whereIn('id', $visible->pluck('centre_id')->filter()->unique()->values())
            ->orderBy('nom')->get(['id', 'nom']);
        $sectionOptions = Section::query()
            ->whereIn('id', $visible->pluck('section_premier_choix_id')->filter()->unique()->values())
            ->orderBy('nom')->get(['id', 'nom']);

        $filterSection = (string) $request->query('section', '');
        $filterCentre  = (string) $request->query('centre', '');

        $candidats = $scopedBase()
            ->when($filterSection !== '', fn (Builder $q) => $q->where('section_premier_choix_id', $filterSection))
            ->when($filterCentre !== '', fn (Builder $q) => $q->where('centre_id', $filterCentre))
            ->orderBy('nom')->orderBy('prenom')
            ->get(['id', 'nom', 'prenom', 'matricule_public']);

        $notes = Note::query()
            ->where('epreuve_id', $epreuve->getKey())
            ->whereIn('candidat_id', $candidats->pluck('id'))
            ->get()
            ->keyBy('candidat_id');

        $payload = $candidats->map(fn ($c) => [
            'id'               => $c->id,
            'nom'              => $c->nom,
            'prenom'           => $c->prenom,
            'matricule_public' => $c->matricule_public,
            'note'             => $notes->get($c->id)?->only(['valeur', 'absent', 'locked', 'commentaire']),
        ]);

        return view('concours::admin.notes.grid', [
            'epreuve'        => $epreuve->loadMissing('typeEpreuve'),
            'candidats'      => $payload,
            'canEnter'       => $canEnter,
            'centreOptions'  => $centreOptions,
            'sectionOptions' => $sectionOptions,
            'filterSection'  => $filterSection,
            'filterCentre'   => $filterCentre,
        ]);
    }

    private function canAccessNotes(Request $request): bool
    {
        return $this->checker->can($request->user(), 'enter:notes:*')
            || $this->checker->can($request->user(), 'enter:notes:own_center')
            || $this->checker->can($request->user(), 'view:notes:own_center');
    }
}
