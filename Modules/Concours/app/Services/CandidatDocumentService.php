<?php

declare(strict_types=1);

namespace Modules\Concours\Services;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Http\UploadedFile;
use Modules\Concours\Models\Candidat;
use Modules\Concours\Models\CandidatDocument;
use Modules\Referentiels\Models\DocumentRequis;

/**
 * Uploads and persists candidate documents (photo + required pieces).
 *
 * Per-document validation comes from the DocumentRequis row itself:
 *   - formats_acceptes ['pdf','jpg',...]
 *   - taille_max_ko
 * which means the admin can tighten / loosen accepted formats from the
 * back-office without a redeploy.
 *
 * Layout on disk (private):
 *   candidats/{annee}/{candidat_id}/photo.{ext}
 *   candidats/{annee}/{candidat_id}/documents/{doc_code}.{ext}
 */
final class CandidatDocumentService
{
    public function __construct(
        private readonly FilesystemManager $files,
    ) {}

    public function storePhoto(Candidat $candidat, UploadedFile $photo, string $anneeCode): void
    {
        $this->validatePhoto($photo);

        $disk = $this->disk();
        $ext = mb_strtolower($photo->getClientOriginalExtension() ?: $photo->guessExtension() ?: 'jpg');
        $path = sprintf('candidats/%s/%s/photo.%s', $anneeCode, $candidat->getKey(), $ext);

        $disk->putFileAs(dirname($path), $photo, basename($path));

        $candidat->forceFill([
            'photo_path' => $path,
            'photo_disk' => $this->diskName(),
        ])->save();
    }

    public function storeDocument(
        Candidat $candidat,
        DocumentRequis $required,
        UploadedFile $file,
        string $anneeCode,
    ): CandidatDocument {
        $this->validateAgainst($required, $file);

        $disk = $this->disk();
        $ext = mb_strtolower($file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'pdf');
        $path = sprintf(
            'candidats/%s/%s/documents/%s.%s',
            $anneeCode,
            $candidat->getKey(),
            $required->code,
            $ext,
        );

        // Compute hash before move (UploadedFile is destroyed after storage)
        $sha256 = hash_file('sha256', $file->getRealPath()) ?: null;

        $disk->putFileAs(dirname($path), $file, basename($path));

        return CandidatDocument::query()->updateOrCreate(
            [
                'candidat_id'         => $candidat->getKey(),
                'document_requis_id'  => $required->getKey(),
            ],
            [
                'file_path'     => $path,
                'disk'          => $this->diskName(),
                'mime_type'     => $file->getMimeType() ?? 'application/octet-stream',
                'size_bytes'    => $file->getSize() ?: 0,
                'original_name' => mb_substr((string) $file->getClientOriginalName(), 0, 190),
                'sha256'        => $sha256,
                'uploaded_at'   => now(),
            ],
        );
    }

    private function validatePhoto(UploadedFile $file): void
    {
        $ext = mb_strtolower($file->getClientOriginalExtension() ?: $file->guessExtension() ?: '');
        if (! in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            throw new \InvalidArgumentException("Photo : format {$ext} non accepté (jpg/png/webp).");
        }
        $maxBytes = 4 * 1024 * 1024; // 4 MB
        if ($file->getSize() > $maxBytes) {
            throw new \InvalidArgumentException('Photo : taille maximum 4 Mo.');
        }
    }

    private function validateAgainst(DocumentRequis $required, UploadedFile $file): void
    {
        $ext = mb_strtolower($file->getClientOriginalExtension() ?: $file->guessExtension() ?: '');
        $accepted = (array) ($required->formats_acceptes ?? []);
        if ($accepted !== [] && ! in_array($ext, $accepted, true)) {
            throw new \InvalidArgumentException(sprintf(
                'Document « %s » : format .%s non accepté (autorisés : %s).',
                $required->libelle,
                $ext,
                implode(', ', $accepted),
            ));
        }
        $maxBytes = $required->taille_max_ko * 1024;
        if ($file->getSize() > $maxBytes) {
            throw new \InvalidArgumentException(sprintf(
                'Document « %s » : taille maximum %d Ko (envoyé %d Ko).',
                $required->libelle,
                $required->taille_max_ko,
                (int) ($file->getSize() / 1024),
            ));
        }
    }

    private function disk(): Filesystem
    {
        return $this->files->disk($this->diskName());
    }

    private function diskName(): string
    {
        return (string) config('concours.storage.disk', 'local');
    }
}
