<?php

declare(strict_types=1);

namespace Modules\Concours\Http\Controllers\Admin\Pages;

use App\Foundation\Permissions\PermissionChecker;
use App\Foundation\Permissions\ScopedQuery;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Concours\Models\Candidat;
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
        if (! $this->checker->can($request->user(), 'enter:notes:*')
            && ! $this->checker->can($request->user(), 'enter:notes:own_center')) {
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
        if (! $this->checker->can($request->user(), 'enter:notes:*')
            && ! $this->checker->can($request->user(), 'enter:notes:own_center')) {
            abort(Response::HTTP_FORBIDDEN);
        }

        // Scope candidates to chef's centre when applicable.
        $query = $epreuve->eligibleCandidatsQuery();
        $query = $this->scoped->apply($query, $request->user(), 'view', 'candidats');

        $candidats = $query
            ->where('statut', '!=', Candidat::STATUS_REJETE)
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
            'epreuve'   => $epreuve->loadMissing('typeEpreuve'),
            'candidats' => $payload,
        ]);
    }
}
