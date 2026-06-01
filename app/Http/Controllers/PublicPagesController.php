<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Modules\Concours\Models\ConcoursSession;
use Modules\Parametrage\Services\SettingsService;
use Symfony\Component\HttpFoundation\Response;

/**
 * Misc public content pages + file streaming.
 *
 *   GET /documents-officiels                  → list of official documents
 *   GET /documents-officiels/{i}/view         → stream a doc inline (PDF viewer)
 *   GET /documents-officiels/{i}/download      → download a doc
 *   GET /annonce                              → the session flyer + share
 *   GET /annonce/flyer                        → stream the flyer
 *
 * Files are streamed THROUGH Laravel (response()->file / Storage::download)
 * rather than via the public-disk /storage symlink — on the LWS shared host
 * Apache won't follow that symlink (CageFS / FollowSymLinks) and returns 403.
 * Streaming reads the file from disk in PHP, so it works regardless.
 */
final class PublicPagesController extends Controller
{
    public function documentsOfficiels(SettingsService $settings): View
    {
        $documents = collect((array) $settings->get('site.documents_officiels', []))
            ->map(fn (array $d, int $i): array => [
                'index' => $i,
                'title' => (string) ($d['title'] ?? 'Document'),
                'type'  => (string) ($d['type'] ?? (str_ends_with(mb_strtolower((string) ($d['file'] ?? '')), '.pdf') ? 'pdf' : 'file')),
                'file'  => (string) ($d['file'] ?? ''),
            ])
            ->filter(fn (array $d): bool => $d['file'] !== '')
            ->values()
            ->all();

        return view('public.documents-officiels', ['documents' => $documents]);
    }

    public function documentView(int $index, SettingsService $settings): Response
    {
        return $this->streamDocument($index, $settings, download: false);
    }

    public function documentDownload(int $index, SettingsService $settings): Response
    {
        return $this->streamDocument($index, $settings, download: true);
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

        // Inline — the <a download> on the page forces a download when needed.
        return response()->file($disk->path($session->flyer_path));
    }

    // ----------------------------------------------------- helpers

    private function streamDocument(int $index, SettingsService $settings, bool $download): Response
    {
        $docs = (array) $settings->get('site.documents_officiels', []);
        $file = (string) ($docs[$index]['file'] ?? '');
        $disk = Storage::disk('public');

        if ($file === '' || ! $disk->exists($file)) {
            abort(404);
        }

        $name = basename($file);

        return $download
            ? $disk->download($file, $name)
            : response()->file($disk->path($file));
    }
}
