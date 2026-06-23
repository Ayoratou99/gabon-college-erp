<?php

declare(strict_types=1);

namespace Modules\Concours\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Http\Response;
use Modules\AcademicStructure\Models\Cycle;
use Modules\Concours\Models\Candidat;

/**
 * On-demand PDF generation for the public candidat documents:
 *
 *   ficheCandidat($c)        → printable inscription form
 *   emploiDuTemps($c)        → exam timetable (planning) for the candidat
 *
 * Both are rendered from Blade templates under resources/views/pdf/.
 */
final class CandidatPdfService
{
    public function __construct(
        private readonly PlanningService $planning,
        private readonly FilesystemManager $files,
    ) {}

    /**
     * Render the inscription fiche.
     *
     * @param  bool  $inline  true → Content-Disposition: inline (renders in
     *                       an iframe / modal; admin preview path).
     *                       false → Content-Disposition: attachment (file
     *                       save dialog; public download path).
     */
    public function ficheCandidat(Candidat $candidat, bool $inline = false): Response
    {
        $candidat->loadMissing([
            'session.anneeAcademique',
            'centre',
            'nationalite',
            'serieBac',
            'premierChoix',
            'secondChoix',
            'documents.documentRequis',
        ]);

        // Build a single payload the blade can iterate over to reproduce the
        // legacy "Cycle | logo | sections-with-checkboxes" table.
        $cycles = Cycle::query()
            ->where('active', true)
            ->with(['sections' => fn ($q) => $q->where('active', true)
                ->orderBy('display_order')->orderBy('nom')])
            ->orderBy('display_order')->orderBy('nom')
            ->get();

        $pdf = Pdf::loadView('concours::pdf.fiche-candidat', [
            'candidat'  => $candidat,
            'cycles'    => $cycles,
            // Resolve the candidat photo into a data URI so DomPDF embeds it
            // without ever issuing an HTTP request (it has no session, no auth,
            // and our photo route is gated). Returns null when no photo found
            // — the blade renders an empty grey box in that case (matches the
            // legacy template's behaviour for un-photographed candidats).
            'photoData' => $this->resolveCandidatPhotoDataUri($candidat),
        ])->setPaper('a4', 'portrait');

        $filename = sprintf('fiche-%s.pdf', strtolower($candidat->matricule_public ?? 'candidat'));
        // `stream()` keeps the binary in the response body but uses inline
        // disposition by default, so the browser embeds it in the doc-preview
        // modal instead of triggering a save dialog like `download()` does.
        return $inline ? $pdf->stream($filename) : $pdf->download($filename);
    }

    /**
     * Resolve the candidat photo into an inline `data:image/...;base64,...`
     * URI, so DomPDF can render it without needing an HTTP fetch. Mirrors
     * the on-disk probe in CandidatDocumentController::resolvePhotoPath()
     * — legacy candidats with `legacy_id` get checked against the
     * imageprofilecupk conventions in the legacy_photos disk.
     */
    private function resolveCandidatPhotoDataUri(Candidat $candidat): ?string
    {
        $candidates = [];

        if (! empty($candidat->photo_path)) {
            $candidates[] = [$candidat->photo_disk ?: 'local', $candidat->photo_path];
        }

        if ($candidat->legacy_id !== null && (int) $candidat->legacy_id > 0) {
            $idetu = (int) $candidat->legacy_id;
            $annee = $this->legacyAnneeFor($candidat);
            $patterns = $annee !== null
                ? ["{$annee}user{$idetu}", "{$annee}user{$idetu}profile",
                   "{$annee}user{$idetu}profil", "{$annee}user{$idetu}photo",
                   "user{$idetu}", (string) $idetu]
                : ["user{$idetu}", (string) $idetu];
            $extensions = ['jpg', 'jpeg', 'png', 'webp', 'JPG', 'JPEG', 'PNG', 'WEBP'];
            foreach ($patterns as $base) {
                foreach ($extensions as $ext) {
                    $candidates[] = ['legacy_photos', "{$base}.{$ext}"];
                }
            }
        }

        foreach ($candidates as [$diskName, $path]) {
            try {
                $disk = $this->files->disk($diskName);
                if (! $disk->exists($path)) {
                    continue;
                }
                $absolute = method_exists($disk, 'path') ? $disk->path($path) : null;
                $bytes = $absolute !== null && is_readable($absolute)
                    ? file_get_contents($absolute)
                    : $disk->get($path);
                if ($bytes === false || $bytes === null || $bytes === '') {
                    continue;
                }
                $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION) ?: 'jpg');
                $mime = match ($ext) {
                    'jpg', 'jpeg' => 'image/jpeg',
                    'png'         => 'image/png',
                    'webp'        => 'image/webp',
                    'gif'         => 'image/gif',
                    default       => 'image/jpeg',
                };
                return 'data:' . $mime . ';base64,' . base64_encode($bytes);
            } catch (\Throwable) {
                // Disk not configured or path traversal — skip silently.
                continue;
            }
        }
        return null;
    }

    /** Recover "2025" from a "2024-2025" / "2025" annee code, or the session date_concours year. */
    private function legacyAnneeFor(Candidat $candidat): ?string
    {
        $session = $candidat->session;
        if ($session === null) {
            return null;
        }
        $code = $session->anneeAcademique?->code;
        if ($code !== null && $code !== '') {
            if (preg_match('/(\d{4})\D+(\d{4})/', (string) $code, $m)) { return $m[2]; }
            if (preg_match('/(\d{4})/', (string) $code, $m))           { return $m[1]; }
        }
        return $session->date_concours?->format('Y') ?? null;
    }

    public function emploiDuTemps(Candidat $candidat, bool $inline = false): Response
    {
        $candidat->loadMissing(['session.anneeAcademique', 'centre', 'premierChoix']);
        $planning = $this->planning->planningForCandidat($candidat);

        $pdf = Pdf::loadView('concours::pdf.emploi-du-temps', [
            'candidat' => $candidat,
            'planning' => $planning,
        ])->setPaper('a4', 'portrait');

        $filename = sprintf('emploi-%s.pdf', strtolower($candidat->matricule_public ?? 'candidat'));
        return $inline ? $pdf->stream($filename) : $pdf->download($filename);
    }
}
