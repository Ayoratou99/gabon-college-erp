<?php

declare(strict_types=1);

namespace Modules\Concours\Http\Controllers\Admin;

use App\Foundation\Permissions\PermissionChecker;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Modules\Concours\Models\Candidat;
use Modules\Concours\Models\CandidatDocument;
use Modules\Concours\Models\CandidatModification;
use Modules\Concours\Services\CandidatDocumentService;
use Modules\Concours\Services\CandidatPdfService;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Per-document review workflow for the admin candidat detail page.
 *
 *   GET    /admin/concours/candidats/{candidat}/documents/{doc}/preview
 *          Stream the file inline (PDFs render in an iframe, images in
 *          an <img>) so chef-centre can review without downloading.
 *
 *   POST   /admin/concours/candidats/{candidat}/documents/{doc}/review
 *          Body: status=valide|a_refaire + (required for a_refaire) comment.
 *          Writes the row + a CandidatModification audit entry.
 *
 *   POST   /admin/concours/candidats/{candidat}/documents/{doc}/replace
 *          Body: file=<new upload>. Soft-deletes the old row, creates a
 *          new one (status = en_attente), audits the replacement.
 *
 *   POST   /admin/concours/candidats/{candidat}/documents/bulk-validate
 *          Flip every `en_attente` doc to `valide` in one go. Useful when
 *          a chef-centre eyeballs a dossier and is happy with everything.
 *
 * All endpoints share the same permission gate: the user must have
 * `edit:candidats:*` OR `edit:candidats:own_center` scoped to the
 * candidat's centre. Preview also accepts `view:candidats:own_center`.
 */
final class CandidatDocumentController extends Controller
{
    public function __construct(
        private readonly PermissionChecker $checker,
        private readonly FilesystemManager $files,
        private readonly CandidatDocumentService $documents,
        private readonly CandidatPdfService $pdfs,
    ) {}

    /**
     * Stream the binary inline. Disposition is `inline` (not attachment)
     * so the browser renders it in the modal's iframe / img. Cache headers
     * are short — admins sometimes re-upload + re-review in the same
     * minute and we don't want a stale copy.
     */
    public function preview(Request $request, Candidat $candidat, CandidatDocument $doc): BinaryFileResponse|StreamedResponse|Response
    {
        $this->ensureViewable($request, $candidat);
        $this->ensureBelongsToCandidat($candidat, $doc);

        $disk = $this->files->disk($doc->disk ?: 'local');
        if (! $disk->exists($doc->file_path)) {
            abort(404, 'Fichier introuvable sur le disque.');
        }

        $mime = $doc->mime_type ?: 'application/octet-stream';
        $name = $doc->original_name ?: basename($doc->file_path);

        // The local disk lets us serve the file directly with proper
        // Content-Type. For S3-like disks we'd switch to a streamed
        // response; the configuration is local/private here so this path
        // is the hot one.
        $absolute = $disk->path($doc->file_path);
        return response()->file($absolute, [
            'Content-Type'        => $mime,
            'Content-Disposition' => $this->inlineDisposition($name),
            'Cache-Control'       => 'private, max-age=60',
            // The iframe needs same-origin to display; we don't allow
            // cross-frame embedding from anywhere else.
            'X-Frame-Options'     => 'SAMEORIGIN',
        ]);
    }

    /**
     * Stream the candidat's identity photo. Same auth gate as preview() so a
     * chef-centre can see the photo of any candidat in their centre.
     *
     * Resolution order:
     *  1. If `photo_path` is stamped (post-cutover candidat or legacy candidat
     *     that was re-photographed), serve directly from `photo_disk`.
     *  2. Else if `legacy_id` is set, probe the `legacy_photos` disk (mounted
     *     at LEGACY_PROFILE_IMAGES_PATH = imageprofilecupk/) with a small
     *     ordered list of legacy filename conventions × extensions. The first
     *     match wins. This is how a chef-centre still sees photos of the
     *     1100+ legacy candidats once the production folder is in place.
     *  3. Otherwise 404.
     */
    public function photo(Request $request, Candidat $candidat): BinaryFileResponse|StreamedResponse|Response
    {
        $this->ensureViewable($request, $candidat);

        [$absolutePath, $ext] = $this->resolvePhotoPath($candidat);
        if ($absolutePath === null) {
            abort(404, 'Aucune photo pour ce candidat.');
        }

        $mime = match (mb_strtolower($ext)) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png'         => 'image/png',
            'webp'        => 'image/webp',
            'gif'         => 'image/gif',
            default       => 'application/octet-stream',
        };

        return response()->file($absolutePath, [
            'Content-Type'    => $mime,
            'Cache-Control'   => 'private, max-age=300',
            'X-Frame-Options' => 'SAMEORIGIN',
        ]);
    }

    /**
     * Resolve the on-disk photo path for a candidat, falling back to the
     * legacy imageprofilecupk folder via filename probing.
     *
     * @return array{0: string|null, 1: string}  [absolutePath|null, extension]
     */
    private function resolvePhotoPath(Candidat $candidat): array
    {
        // Path 1: stamped photo_path on the configured disk.
        if (! empty($candidat->photo_path)) {
            $disk = $this->files->disk($candidat->photo_disk ?: 'local');
            if ($disk->exists($candidat->photo_path)) {
                return [
                    $disk->path($candidat->photo_path),
                    pathinfo($candidat->photo_path, PATHINFO_EXTENSION) ?: 'jpg',
                ];
            }
        }

        // Path 2: legacy candidat fallback — probe the imageprofilecupk folder.
        if ($candidat->legacy_id !== null && (int) $candidat->legacy_id > 0) {
            $disk     = $this->files->disk('legacy_photos');
            $idetu    = (int) $candidat->legacy_id;
            $annee    = $this->legacyAnneeFor($candidat);

            // Profile photos used an UNDERSCORE between "user" and the id:
            //   2025user_1369.png
            // whereas DOCUMENTS did not (2025user1369acte.pdf). The underscore
            // form is the real legacy convention for photos, so it's tried
            // first; the no-underscore + plain forms stay as defensive
            // fallbacks for any odd file that slipped through.
            $patterns = $annee !== null
                ? [
                    "{$annee}user_{$idetu}",
                    "{$annee}user_{$idetu}profile",
                    "{$annee}user_{$idetu}profil",
                    "{$annee}user_{$idetu}photo",
                    "{$annee}user{$idetu}",
                    "{$annee}user{$idetu}profile",
                    "{$annee}user{$idetu}profil",
                    "{$annee}user{$idetu}photo",
                    "user_{$idetu}",
                    "user{$idetu}",
                    "{$idetu}",
                ]
                : [
                    "user_{$idetu}",
                    "user{$idetu}",
                    "{$idetu}",
                ];
            $extensions = ['jpg', 'jpeg', 'png', 'webp', 'JPG', 'JPEG', 'PNG', 'WEBP'];

            foreach ($patterns as $base) {
                foreach ($extensions as $ext) {
                    $candidate = "{$base}.{$ext}";
                    if ($disk->exists($candidate)) {
                        return [$disk->path($candidate), $ext];
                    }
                }
            }
        }

        return [null, 'jpg'];
    }

    /**
     * Recover the legacy année code (e.g. "2025") for filename probing.
     * Legacy filenames embedded it as the leading numeric segment, so the
     * AnneeAcademique code or — failing that — the session's start year is
     * our best shot.
     */
    private function legacyAnneeFor(Candidat $candidat): ?string
    {
        $session = $candidat->session
            ?? \Modules\Concours\Models\ConcoursSession::query()->find($candidat->concours_session_id);
        if ($session === null) {
            return null;
        }

        $code = $session->anneeAcademique?->code;
        if ($code !== null && $code !== '') {
            // Strip a "2024-2025" → "2025" because the legacy filenames used a
            // single year, taking the latter half.
            if (preg_match('/(\d{4})\D+(\d{4})/', (string) $code, $m)) {
                return $m[2];
            }
            if (preg_match('/(\d{4})/', (string) $code, $m)) {
                return $m[1];
            }
        }
        return $session->date_concours?->format('Y') ?? null;
    }

    /**
     * Stream the auto-generated fiche / emploi-du-temps PDF inline for the
     * admin preview modal. Bypasses the public identity-gate form
     * (`concours.public.candidat.pdf`) — admins already passed RBAC + 2FA,
     * we don't make them retype the candidat's email and tel.
     *
     *   GET /api/admin/concours/candidats/{candidat}/pdf/{document}
     *
     * `document` is "fiche" or "emploi-du-temps" — same vocabulary as the
     * public route so the rest of the codebase stays uniform.
     */
    public function pdf(Request $request, Candidat $candidat, string $document): Response
    {
        $this->ensureViewable($request, $candidat);

        return match ($document) {
            'fiche'           => $this->pdfs->ficheCandidat($candidat, inline: true),
            'emploi-du-temps' => $this->pdfs->emploiDuTemps($candidat, inline: true),
            default           => abort(Response::HTTP_NOT_FOUND, 'Document inconnu.'),
        };
    }

    /**
     * Update the review status. Writes:
     *   - candidat_documents.{review_status, reviewed_at, reviewed_by_user_id, review_comment}
     *   - candidat_modifications (channel=admin, field=document.<code>.review_status)
     */
    public function review(Request $request, Candidat $candidat, CandidatDocument $doc): JsonResponse
    {
        $this->ensureEditable($request, $candidat);
        $this->ensureSessionOpenForEdit($candidat);
        $this->ensureBelongsToCandidat($candidat, $doc);

        $data = Validator::make($request->all(), [
            'status'  => ['required', 'in:' . CandidatDocument::REVIEW_APPROVED . ',' . CandidatDocument::REVIEW_REJECTED],
            // A rejection MUST include a reason — that's the message the
            // candidat will see in the rejection email + on the modify form.
            'comment' => ['required_if:status,' . CandidatDocument::REVIEW_REJECTED, 'nullable', 'string', 'max:500'],
        ])->validate();

        $oldStatus = $doc->review_status;

        DB::transaction(function () use ($doc, $candidat, $data, $request, $oldStatus): void {
            $doc->forceFill([
                'review_status'       => $data['status'],
                'reviewed_at'         => now(),
                'reviewed_by_user_id' => $request->user()?->getAuthIdentifier(),
                'review_comment'      => $data['comment'] ?? null,
            ])->save();

            $code = $doc->documentRequis?->code ?? 'inconnu';
            CandidatModification::query()->create([
                'candidat_id' => $candidat->getKey(),
                'user_id'     => $request->user()?->getAuthIdentifier(),
                'channel'     => CandidatModification::CHANNEL_ADMIN,
                'field'       => "document.{$code}.review_status",
                'old_value'   => $oldStatus,
                'new_value'   => $data['status'],
                'reason'      => $data['comment'] ?? null,
                'ip_address'  => $request->ip(),
                'changed_at'  => now(),
            ]);
        });

        return response()->json([
            'ok'             => true,
            'doc_id'         => $doc->getKey(),
            'review_status'  => $doc->review_status,
            'reviewed_at'    => $doc->reviewed_at?->toIso8601String(),
            'reviewed_by'    => trim(($request->user()?->prenom ?? '') . ' ' . ($request->user()?->nom ?? '')) ?: null,
            'review_comment' => $doc->review_comment,
        ]);
    }

    /**
     * Replace an existing document with a new upload (e.g. chef-centre
     * fixes a blurry scan on behalf of a candidat at the centre).
     */
    public function replace(Request $request, Candidat $candidat, CandidatDocument $doc): JsonResponse
    {
        $this->ensureEditable($request, $candidat);
        $this->ensureSessionOpenForEdit($candidat);
        $this->ensureBelongsToCandidat($candidat, $doc);

        $required = $doc->documentRequis;
        if ($required === null) {
            return response()->json(['ok' => false, 'error' => 'Type de document inconnu.'], 422);
        }

        $data = Validator::make($request->all(), [
            'file' => ['required', 'file', 'max:10240'],
        ])->validate();

        /** @var \Illuminate\Http\UploadedFile $file */
        $file = $request->file('file');

        $session = $candidat->session;
        $anneeCode = $session?->anneeAcademique?->code ?? date('Y');

        DB::transaction(function () use ($doc, $candidat, $required, $file, $anneeCode, $request): void {
            $oldOriginal = $doc->original_name ?: basename((string) $doc->file_path);

            // storeDocument does an updateOrCreate on (candidat_id,
            // document_requis_id) — that's the unique pair, so it overwrites
            // file_path / sha256 / mime / size atomically and resets the
            // review state because we explicitly null reviewed_at below.
            $this->documents->storeDocument($candidat, $required, $file, $anneeCode);

            // Reset review state on the (now-updated) row.
            $doc->refresh();
            $doc->forceFill([
                'review_status'       => CandidatDocument::REVIEW_PENDING,
                'reviewed_at'         => null,
                'reviewed_by_user_id' => null,
                'review_comment'      => null,
            ])->save();

            CandidatModification::query()->create([
                'candidat_id' => $candidat->getKey(),
                'user_id'     => $request->user()?->getAuthIdentifier(),
                'channel'     => CandidatModification::CHANNEL_ADMIN,
                'field'       => "document.{$required->code}.file",
                'old_value'   => $oldOriginal,
                'new_value'   => $file->getClientOriginalName(),
                'reason'      => 'Remplacement du fichier par un administrateur',
                'ip_address'  => $request->ip(),
                'changed_at'  => now(),
            ]);
        });

        $doc->refresh();
        return response()->json([
            'ok'             => true,
            'doc_id'         => $doc->getKey(),
            'original_name'  => $doc->original_name,
            'size_kb'        => (int) round(((int) $doc->size_bytes) / 1024),
            'review_status'  => $doc->review_status,
        ]);
    }

    /**
     * Flip every pending document of this candidat to "valide" in one POST.
     */
    public function bulkValidate(Request $request, Candidat $candidat): RedirectResponse
    {
        $this->ensureEditable($request, $candidat);
        $this->ensureSessionOpenForEdit($candidat);

        $userId = $request->user()?->getAuthIdentifier();
        $count = 0;

        DB::transaction(function () use ($candidat, $userId, $request, &$count): void {
            $pending = CandidatDocument::query()
                ->with('documentRequis:id,code')
                ->where('candidat_id', $candidat->getKey())
                ->where('review_status', CandidatDocument::REVIEW_PENDING)
                ->get();

            foreach ($pending as $doc) {
                $doc->forceFill([
                    'review_status'       => CandidatDocument::REVIEW_APPROVED,
                    'reviewed_at'         => now(),
                    'reviewed_by_user_id' => $userId,
                ])->save();

                $code = $doc->documentRequis?->code ?? 'inconnu';
                CandidatModification::query()->create([
                    'candidat_id' => $candidat->getKey(),
                    'user_id'     => $userId,
                    'channel'     => CandidatModification::CHANNEL_ADMIN,
                    'field'       => "document.{$code}.review_status",
                    'old_value'   => CandidatDocument::REVIEW_PENDING,
                    'new_value'   => CandidatDocument::REVIEW_APPROVED,
                    'reason'      => 'Validation groupée',
                    'ip_address'  => $request->ip(),
                    'changed_at'  => now(),
                ]);
                $count++;
            }
        });

        return back()->with('status', $count === 0
            ? 'Aucun document en attente à valider.'
            : "{$count} document(s) validé(s).");
    }

    // ----------------------------------------------------- helpers

    private function ensureViewable(Request $request, Candidat $candidat): void
    {
        $user = $request->user();
        if ($user === null
            || (! $this->checker->can($user, 'view:candidats:*', $candidat)
                && ! $this->checker->can($user, 'view:candidats:own_center', $candidat))
        ) {
            throw new HttpException(403, 'Vous n\'avez pas l\'autorisation de consulter ce dossier.');
        }
    }

    private function ensureEditable(Request $request, Candidat $candidat): void
    {
        $user = $request->user();
        if ($user === null
            || (! $this->checker->can($user, 'edit:candidats:*', $candidat)
                && ! $this->checker->can($user, 'edit:candidats:own_center', $candidat))
        ) {
            throw new HttpException(403, 'Vous n\'avez pas l\'autorisation de modifier ce dossier.');
        }
    }

    /**
     * Refuse state-changing operations once the session is no longer accepting
     * inscriptions. Read-only flows (preview, photo) deliberately bypass this —
     * archives must stay browseable for legacy concours.
     *
     * View-layer gating in show.blade.php hides the buttons proactively; this
     * is the controller-side defence-in-depth.
     */
    private function ensureSessionOpenForEdit(Candidat $candidat): void
    {
        $session = $candidat->session
            ?? \Modules\Concours\Models\ConcoursSession::query()->find($candidat->concours_session_id);

        if ($session === null || ! $session->isInscriptionOpen()) {
            throw new HttpException(
                409,
                "La session d'inscriptions est clôturée — aucune modification du dossier n'est plus possible.",
            );
        }
    }

    private function ensureBelongsToCandidat(Candidat $candidat, CandidatDocument $doc): void
    {
        if ((string) $doc->candidat_id !== (string) $candidat->getKey()) {
            // Don't surface this as 404 to avoid leaking which docs exist —
            // 403 keeps the failure shape identical to a permission denial.
            throw new HttpException(403, 'Ce document n\'appartient pas à ce dossier.');
        }
    }

    /**
     * RFC 6266 "filename*" + an ASCII fallback so non-Latin chars in
     * `original_name` (a French accent, a Cyrillic letter, etc.) survive
     * the Content-Disposition trip.
     */
    private function inlineDisposition(string $filename): string
    {
        $ascii = preg_replace('/[^A-Za-z0-9._\- ]/', '_', $filename) ?: 'document';
        return sprintf(
            'inline; filename="%s"; filename*=UTF-8\'\'%s',
            $ascii,
            rawurlencode($filename),
        );
    }
}
