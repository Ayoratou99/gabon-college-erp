<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Concours\Models\ConcoursSession;
use Modules\Parametrage\Models\DocumentOfficiel;
use Symfony\Component\HttpFoundation\Response;

/**
 * Misc public content pages + file streaming.
 *
 *   GET /documents-officiels                 → list of official documents
 *   GET /documents-officiels/{d}/view        → stream a doc inline (PDF viewer)
 *   GET /documents-officiels/{d}/download     → download a doc
 *   GET /annonce                             → the session flyer + share
 *   GET /annonce/flyer                       → stream the flyer
 *
 * Files are streamed THROUGH Laravel (response()->file / Storage::download)
 * rather than via the public-disk /storage symlink — on the LWS shared host
 * Apache won't follow that symlink (CageFS / FollowSymLinks) and returns 403.
 */
final class PublicPagesController extends Controller
{
    public function documentsOfficiels(): View
    {
        return view('public.documents-officiels', [
            'documents' => DocumentOfficiel::query()
                ->where('active', true)
                ->orderBy('display_order')->orderBy('title')
                ->get(),
        ]);
    }

    public function documentView(DocumentOfficiel $document): Response
    {
        return $this->streamDoc($document, download: false);
    }

    public function documentDownload(DocumentOfficiel $document): Response
    {
        return $this->streamDoc($document, download: true);
    }

    public function annonce(): View|RedirectResponse
    {
        $session = ConcoursSession::publicCurrent();

        if ($session === null || ! $session->hasFlyer() || ! $session->isInscriptionOpen()) {
            return redirect()->route('home');
        }

        return view('public.annonce', ['session' => $session]);
    }

    public function annonceFlyer(): Response
    {
        $session = ConcoursSession::publicCurrent();
        if ($session === null || ! $session->hasFlyer()) {
            abort(404);
        }

        $disk = Storage::disk($session->flyer_disk ?: 'public');
        if (! $disk->exists($session->flyer_path)) {
            abort(404);
        }

        return response()->file($disk->path($session->flyer_path));
    }

    // ----------------------------------------------------- helpers

    private function streamDoc(DocumentOfficiel $document, bool $download): Response
    {
        $disk = $document->disk();
        if ($document->file_path === null || ! $disk->exists($document->file_path)) {
            abort(404);
        }

        if ($download) {
            $name = Str::slug($document->title) . '.' . pathinfo($document->file_path, PATHINFO_EXTENSION);

            return $disk->download($document->file_path, $name);
        }

        return response()->file($disk->path($document->file_path));
    }
}
