<?php

declare(strict_types=1);

namespace Modules\Concours\Http\Controllers\Etudiant;

use App\Foundation\Permissions\PermissionChecker;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Concours\Models\Candidat;
use Symfony\Component\HttpFoundation\Response;

/**
 * The authenticated étudiant space.
 *
 *   GET /espace-etudiant
 *
 * Reads the étudiant's `User::promoted_from_candidat_id` to find their
 * original Candidat row (the application dossier — kept immutable). The
 * Candidat is shown read-only here; the étudiant edits *their account*
 * (password, 2FA, profile) via the existing /admin/users/{user} surface.
 *
 * Permission: `view:etudiant_space:own`. The active role must be
 * `etudiant` — which the role picker handles cleanly: a user with
 * multiple roles (e.g. ex-chef-centre who's now an étudiant) picks
 * which hat they're wearing at login.
 */
final class EtudiantSpaceController extends Controller
{
    public function __construct(
        private readonly PermissionChecker $checker,
    ) {}

    public function show(Request $request): View|RedirectResponse
    {
        if (! $this->checker->can($request->user(), 'view:etudiant_space:own')) {
            abort(Response::HTTP_FORBIDDEN);
        }

        /** @var \Modules\UserManagement\Models\User $user */
        $user = $request->user();

        // Resolve the Candidat dossier this étudiant was promoted from.
        // Null means the User wasn't created via CandidatPromotionService
        // (could be a manually-attached etudiant role on an admin account);
        // we still render the page but with a "no dossier linked" notice.
        $candidat = $user->promoted_from_candidat_id !== null
            ? Candidat::query()
                ->with([
                    'session:id,code,libelle,date_concours',
                    'centre:id,nom,ville',
                    'sectionOrientation:id,nom,code',
                    'premierChoix:id,nom,code',
                    'documents.documentRequis:id,code,libelle',
                ])
                ->find($user->promoted_from_candidat_id)
            : null;

        return view('concours::etudiant.space', [
            'candidat'   => $candidat,
            'user'       => $user,
            'isAdmis'    => $candidat?->statut === Candidat::STATUS_ADMIS,
        ]);
    }
}
