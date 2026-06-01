<?php

declare(strict_types=1);

namespace Modules\Parametrage\Http\Controllers\Admin\Pages;

use App\Foundation\Permissions\PermissionChecker;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Modules\Parametrage\Models\DocumentOfficiel;
use Symfony\Component\HttpFoundation\Response;

/**
 * Back-office CRUD for the official documents shown on /documents-officiels
 * and the public footer. Files are stored on the public disk.
 */
final class DocumentOfficielController extends Controller
{
    public function __construct(
        private readonly PermissionChecker $checker,
    ) {}

    public function index(Request $request): View
    {
        $this->can($request, 'view:parametrage:*');

        return view('parametrage::admin.documents-officiels', [
            'documents' => DocumentOfficiel::query()->orderBy('display_order')->orderBy('title')->get(),
            'canEdit'   => $this->checker->can($request->user(), 'edit:parametrage:*'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->can($request, 'edit:parametrage:*');

        $data = Validator::validate($request->all(), [
            'title' => ['required', 'string', 'max:191'],
            'file'  => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:20480'],
        ]);

        $file = $request->file('file');
        DocumentOfficiel::query()->create([
            'title'         => $data['title'],
            'file_path'     => $file->store('documents-officiels', 'public'),
            'file_disk'     => 'public',
            'mime_type'     => $file->getMimeType(),
            'size_bytes'    => $file->getSize(),
            'display_order' => (int) DocumentOfficiel::query()->max('display_order') + 1,
            'active'        => true,
        ]);

        return back()->with('status', 'Document officiel ajouté.');
    }

    public function update(Request $request, DocumentOfficiel $document): RedirectResponse
    {
        $this->can($request, 'edit:parametrage:*');

        $data = Validator::validate($request->all(), [
            'title'  => ['required', 'string', 'max:191'],
            'active' => ['sometimes', 'boolean'],
            'file'   => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:20480'],
        ]);

        $document->title  = $data['title'];
        $document->active = $request->boolean('active');

        if ($request->hasFile('file')) {
            $old = $document->file_path;
            $file = $request->file('file');
            $document->file_path  = $file->store('documents-officiels', 'public');
            $document->mime_type  = $file->getMimeType();
            $document->size_bytes = $file->getSize();
            $document->save();
            if ($old && $old !== $document->file_path) {
                $document->disk()->delete($old);
            }
        } else {
            $document->save();
        }

        return back()->with('status', 'Document officiel mis à jour.');
    }

    public function destroy(Request $request, DocumentOfficiel $document): RedirectResponse
    {
        $this->can($request, 'edit:parametrage:*');

        $path = $document->file_path;
        $disk = $document->disk();
        $document->delete();
        if ($path !== null && $path !== '') {
            $disk->delete($path);
        }

        return back()->with('status', 'Document officiel supprimé.');
    }

    private function can(Request $request, string $perm): void
    {
        if (! $this->checker->can($request->user(), $perm)) {
            abort(Response::HTTP_FORBIDDEN);
        }
    }
}
