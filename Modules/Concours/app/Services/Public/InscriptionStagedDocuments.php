<?php

declare(strict_types=1);

namespace Modules\Concours\Services\Public;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Contracts\Session\Session;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Http\UploadedFile;
use Ramsey\Uuid\Uuid;

/**
 * Per-visitor file staging area for the inscription wizard.
 *
 * Why? PHP / Apache caps `post_max_size` and `upload_max_filesize` around
 * 8-20 Mo by default. A candidat with a 4 Mo photo + 6 documents at 10 Mo
 * each blows past that immediately and gets a useless "Request Entity Too
 * Large" error before any application code runs. Worse: by the time they
 * see the error, they've burned a megabit of mobile data they can't get
 * back.
 *
 * Solution: each file is POSTed alone via AJAX to a small stage endpoint.
 * Per-request payload tops out at ~10 Mo (one document) regardless of how
 * many documents the candidat ends up submitting. We store each file under
 *
 *     inscription-drafts/{visitor_uuid}/{code}.{ext}
 *
 * on the configured disk, keep its metadata in the visitor's session
 * (original_name, mime, size_bytes, sha256), and replay them as
 * UploadedFile instances on the final submit so the rest of the pipeline
 * (CandidatDocumentService::storeDocument) stays unchanged.
 *
 * The reserved code 'photo' identifies the identity photo; everything else
 * is a regular documents_requis code.
 *
 * Cleanup: the staging folder is wiped on successful submit, on reset, or
 * via the `inscription:gc` artisan command (entries older than 7 days).
 */
final class InscriptionStagedDocuments
{
    /**
     * Reserved code for the identity photo. Anything else MUST map to a
     * documents_requis.code so validation can route per-document.
     */
    public const PHOTO_CODE = 'photo';

    private const VISITOR_KEY = 'concours.inscription.visitor_id';
    private const FILES_KEY   = 'concours.inscription.staged_documents';

    public function __construct(
        private readonly Session $session,
        private readonly FilesystemManager $files,
    ) {}

    /**
     * Persist one UploadedFile under the visitor's draft folder + return the
     * metadata recorded in the session.
     *
     * @return array{code: string, original_name: string, mime: string, size_bytes: int, ext: string, path: string}
     */
    public function stage(string $code, UploadedFile $file): array
    {
        $visitorId = $this->ensureVisitorId();
        $ext = mb_strtolower($file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'bin');

        $disk = $this->disk();
        $relativePath = $this->stagedPath($visitorId, $code, $ext);

        // If this code already has a staged file, blow it away first so we
        // don't accumulate orphans when the candidat re-picks a file.
        $this->removeFromDiskIfStaged($code);

        $disk->putFileAs(dirname($relativePath), $file, basename($relativePath));

        $meta = [
            'code'          => $code,
            'original_name' => mb_substr((string) $file->getClientOriginalName(), 0, 190),
            'mime'          => $file->getMimeType() ?? 'application/octet-stream',
            'size_bytes'    => $file->getSize() ?: 0,
            'ext'           => $ext,
            'path'          => $relativePath,
        ];

        $map = $this->all();
        $map[$code] = $meta;
        $this->session->put(self::FILES_KEY, $map);

        return $meta;
    }

    /**
     * Drop a staged file (disk + session metadata). No-op if `$code` isn't
     * staged.
     */
    public function remove(string $code): void
    {
        $this->removeFromDiskIfStaged($code);
        $map = $this->all();
        unset($map[$code]);
        $this->session->put(self::FILES_KEY, $map);
    }

    /**
     * Replay a staged file as a Laravel UploadedFile so it can be handed to
     * services that already speak that idiom (CandidatDocumentService).
     */
    public function asUploadedFile(string $code): ?UploadedFile
    {
        $meta = $this->all()[$code] ?? null;
        if ($meta === null) {
            return null;
        }
        $absolute = $this->disk()->path($meta['path']);
        if (! is_file($absolute)) {
            // Session points at a file that no longer exists on disk —
            // probably wiped by another tab. Drop the stale metadata and
            // refuse to replay.
            $this->remove($code);
            return null;
        }
        // 4th arg null = no upload error; 5th arg true = $test mode skips
        // the is_uploaded_file() check (the file lives on the local disk,
        // not in /tmp).
        return new UploadedFile(
            $absolute,
            $meta['original_name'],
            $meta['mime'],
            null,
            true,
        );
    }

    /**
     * Whole map keyed by code, useful for the view ("show me which slots are
     * already filled").
     *
     * @return array<string, array{code: string, original_name: string, mime: string, size_bytes: int, ext: string, path: string}>
     */
    public function all(): array
    {
        $map = $this->session->get(self::FILES_KEY, []);
        return is_array($map) ? $map : [];
    }

    public function has(string $code): bool
    {
        return isset($this->all()[$code]);
    }

    /**
     * Burn the whole staging folder + clear the session pointer. Called on
     * successful registration and on explicit reset.
     */
    public function wipeAll(): void
    {
        $visitorId = $this->session->get(self::VISITOR_KEY);
        if (is_string($visitorId) && $visitorId !== '') {
            $disk = $this->disk();
            $folder = "inscription-drafts/{$visitorId}";
            if ($disk->exists($folder)) {
                $disk->deleteDirectory($folder);
            }
        }
        $this->session->forget([self::VISITOR_KEY, self::FILES_KEY]);
    }

    private function ensureVisitorId(): string
    {
        $existing = $this->session->get(self::VISITOR_KEY);
        if (is_string($existing) && $existing !== '') {
            return $existing;
        }
        $id = (string) Uuid::uuid4();
        $this->session->put(self::VISITOR_KEY, $id);
        return $id;
    }

    private function stagedPath(string $visitorId, string $code, string $ext): string
    {
        // Sanitize: codes come from validated input but we belt-and-suspender
        // strip anything weird before letting it touch the filesystem.
        $safeCode = preg_replace('/[^A-Za-z0-9_\-]/', '', $code) ?: 'doc';
        $safeExt  = preg_replace('/[^A-Za-z0-9]/', '', $ext) ?: 'bin';
        return "inscription-drafts/{$visitorId}/{$safeCode}.{$safeExt}";
    }

    private function removeFromDiskIfStaged(string $code): void
    {
        $meta = $this->all()[$code] ?? null;
        if ($meta === null) {
            return;
        }
        $disk = $this->disk();
        if ($disk->exists($meta['path'])) {
            $disk->delete($meta['path']);
        }
    }

    private function disk(): Filesystem
    {
        return $this->files->disk((string) config('concours.storage.disk', 'local'));
    }
}
