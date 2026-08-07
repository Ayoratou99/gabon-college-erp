<?php

declare(strict_types=1);

namespace Modules\Concours\Http\Controllers\Admin\Pages;

use App\Foundation\Permissions\PermissionChecker;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Modules\AcademicStructure\Models\Section;
use Modules\Concours\Exports\GagnantsExport;
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

    /**
     * Attach (or replace) the final procès-verbal on the active publication.
     * The session cannot be closed until this file exists — see
     * ResultPublication::hasPv() and the guard in SessionPageController.
     *
     *   POST /admin/concours/selection/pv
     */
    public function uploadPv(Request $request): RedirectResponse
    {
        if (! $this->checker->can($request->user(), 'publish:results:*')) {
            abort(Response::HTTP_FORBIDDEN);
        }

        $session = ConcoursSession::active();
        $publication = $session ? ResultPublication::latestActiveFor($session->id) : null;
        if ($publication === null) {
            return back()->withErrors(['pv' => 'Aucune publication active — publiez les résultats d\'abord.']);
        }

        $request->validate(['pv' => ['required', 'file', 'mimes:pdf', 'max:20480']]);

        $old  = $publication->fichier_path;
        $path = $request->file('pv')->store('publications', 'local');

        $publication->forceFill([
            'fichier_path' => $path,
            'fichier_disk' => 'local',
        ])->save();

        if ($old && $old !== $path) {
            Storage::disk($publication->fichier_disk ?: 'local')->delete($old);
        }

        return back()->with('status', 'Procès-verbal final enregistré.');
    }

    /**
     * Excel workbook of the winners, one sheet per section of orientation.
     *
     *   GET /admin/concours/selection/gagnants.xlsx
     */
    public function exportGagnants(Request $request): Response
    {
        if (! $this->checker->can($request->user(), 'publish:results:*')) {
            abort(Response::HTTP_FORBIDDEN);
        }

        $session = ConcoursSession::active();
        if ($session === null) {
            abort(Response::HTTP_NOT_FOUND, 'Aucune session active.');
        }

        return Excel::download(
            new GagnantsExport(
                session: $session,
                includeSecretId: $this->checker->can($request->user(), 'view:identifiant_secret:*'),
            ),
            sprintf('gagnants-%s-%s.xlsx', $session->code ?? 'session', now()->format('Ymd-His')),
        );
    }
}
