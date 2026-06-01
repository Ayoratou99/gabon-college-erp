<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Modules\Concours\Models\ConcoursSession;
use Modules\Parametrage\Services\SettingsService;

/**
 * Misc public content pages:
 *   GET /documents-officiels   → list of official documents (PV, règlements…)
 *   GET /annonce               → the active session's announcement flyer + share
 */
final class PublicPagesController extends Controller
{
    /**
     * Official documents — the list lives in the `site.documents_officiels`
     * Parametrage setting so it can be grown / trimmed without a code change.
     */
    public function documentsOfficiels(SettingsService $settings): View
    {
        $documents = collect((array) $settings->get('site.documents_officiels', []))
            ->map(function (array $d): array {
                $file = (string) ($d['file'] ?? '');

                return [
                    'title' => (string) ($d['title'] ?? 'Document'),
                    'type'  => (string) ($d['type'] ?? (str_ends_with(mb_strtolower($file), '.pdf') ? 'pdf' : 'file')),
                    'url'   => $file !== '' ? Storage::disk('public')->url($file) : null,
                ];
            })
            ->filter(fn (array $d): bool => $d['url'] !== null)
            ->values()
            ->all();

        return view('public.documents-officiels', ['documents' => $documents]);
    }

    /**
     * Preview of the public-current session's announcement flyer, with a share
     * action. Only reachable while inscriptions are open and a flyer exists.
     */
    public function annonce(): View|RedirectResponse
    {
        $session = ConcoursSession::publicCurrent();

        if ($session === null || ! $session->hasFlyer() || ! $session->isInscriptionOpen()) {
            return redirect()->route('home');
        }

        return view('public.annonce', ['session' => $session]);
    }
}
