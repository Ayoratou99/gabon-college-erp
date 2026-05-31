<?php

declare(strict_types=1);

namespace Modules\Concours\Http\Controllers\Admin\Pages;

use App\Foundation\Permissions\PermissionChecker;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Modules\AcademicStructure\Models\Section;
use Modules\Referentiels\Models\DocumentRequis;
use Symfony\Component\HttpFoundation\Response;

/**
 * Admin matrix that controls which documents_requis apply to which sections.
 *
 * A document with NO row in the pivot is "universal" — every candidat sees
 * the slot regardless of their section choice. Linking it to one or more
 * sections turns it section-specific: only candidats whose
 * `section_premier_choix_id` is in the linked set see the slot.
 *
 *   GET  /admin/concours/document-requis-sections     → matrix page
 *   POST /admin/concours/document-requis-sections/toggle → flip ONE cell
 *
 * Toggle is one cell at a time (single AJAX POST) to keep the UX snappy —
 * no big "Save" button. Each click is its own audit-traceable action via
 * Eloquent timestamps on the pivot rows.
 *
 * Permission gate: `edit:referentiels:*` (DG / DE / super-admin). Chef-centre
 * has no business setting the catalog of required documents.
 */
final class DocumentRequisSectionMatrixPageController extends Controller
{
    public function __construct(
        private readonly PermissionChecker $checker,
    ) {}

    public function index(Request $request): View
    {
        $this->guardEdit($request);

        $documents = DocumentRequis::query()
            ->where('active', true)
            ->with('sections:id')
            ->ordered()
            ->get(['id', 'code', 'libelle', 'obligatoire']);

        $sections = Section::query()
            ->where('active', true)
            ->where('ouvert_au_concours', true)
            ->orderBy('display_order')
            ->orderBy('nom')
            ->get(['id', 'code', 'nom']);

        // Lookup map: doc id => [section id, ...]. Lets the view render
        // each cell's checked state in O(1) without re-querying.
        $linksByDoc = $documents->mapWithKeys(fn (DocumentRequis $d) => [
            (string) $d->id => $d->sections->pluck('id')->map(fn ($id) => (string) $id)->all(),
        ])->all();

        return view('concours::admin.document_requis_sections.index', [
            'documents'  => $documents,
            'sections'   => $sections,
            'linksByDoc' => $linksByDoc,
        ]);
    }

    /**
     * AJAX: toggle the link between one document and one section.
     *
     *   POST /admin/concours/document-requis-sections/toggle
     *     document_requis_id, section_id, linked (bool: target state)
     *
     * Idempotent: sending `linked=true` for an already-linked pair is a
     * no-op; same for `linked=false` on an unlinked one. The response
     * always echoes the resulting state.
     */
    public function toggle(Request $request): JsonResponse
    {
        $this->guardEdit($request);

        $data = Validator::make($request->all(), [
            'document_requis_id' => ['required', 'uuid', 'exists:documents_requis,id'],
            'section_id'         => ['required', 'uuid', 'exists:sections,id'],
            'linked'             => ['required', 'boolean'],
        ])->validate();

        /** @var DocumentRequis $doc */
        $doc = DocumentRequis::query()->findOrFail($data['document_requis_id']);

        if ($data['linked']) {
            // syncWithoutDetaching is idempotent — no exception on duplicate.
            $doc->sections()->syncWithoutDetaching([$data['section_id']]);
        } else {
            $doc->sections()->detach($data['section_id']);
        }

        return response()->json([
            'ok'                 => true,
            'document_requis_id' => $data['document_requis_id'],
            'section_id'         => $data['section_id'],
            'linked'             => (bool) $data['linked'],
            // Recompute "is universal" so the UI badge updates without
            // a roundtrip — universal flips at the boundary 0 ↔ 1 sections.
            'is_universal'       => $doc->sections()->count() === 0,
        ]);
    }

    private function guardEdit(Request $request): void
    {
        if (! $this->checker->can($request->user(), 'edit:referentiels:*')) {
            abort(Response::HTTP_FORBIDDEN);
        }
    }
}
