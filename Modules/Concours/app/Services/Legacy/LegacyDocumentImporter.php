<?php

declare(strict_types=1);

namespace Modules\Concours\Services\Legacy;

use Modules\Concours\Models\CandidatDocument;
use Modules\Referentiels\Models\DocumentRequis;
use Modules\UserManagement\Services\LegacyDumpParser;

/**
 * Imports `documents_etudiants` → `candidat_documents`.
 *
 *   - File paths are stored as-is (legacy form: ../documentcupk/2025user42acte.pdf).
 *   - `disk` is set to "legacy" so the new file delivery layer routes them
 *     to the read-only mount declared in docker-compose.
 *   - No physical file copy — fully transparent to the legacy folder.
 */
final class LegacyDocumentImporter
{
    public function import(
        LegacyDumpParser $parser,
        LegacyImportContext $context,
        LegacyImportReport $report,
        bool $dryRun,
    ): void {
        $documentByCode = $this->mapLegacyDocumentCodes($parser, $context);

        foreach ($parser->rowsOf('documents_etudiants') as $row) {
            $legacyEtuId = (int) ($row['idetu'] ?? 0);
            $legacyDocId = (int) ($row['iddoc'] ?? 0);
            $srcPath     = (string) ($row['src'] ?? '');

            if ($legacyEtuId === 0 || $legacyDocId === 0 || $srcPath === '') {
                $report->skippedOne('candidat_documents');
                continue;
            }

            try {
                $candidatId = $context->candidatByLegacyId[$legacyEtuId] ?? null;
                $documentId = $context->documentByLegacyId[$legacyDocId] ?? null;

                if ($candidatId === null || $documentId === null) {
                    $report->failedOne('candidat_documents',
                        "{$legacyEtuId}/{$legacyDocId}",
                        'FK manquante (candidat ou documents_requis non importé).',
                    );
                    continue;
                }

                $exists = CandidatDocument::query()
                    ->where('candidat_id', $candidatId)
                    ->where('document_requis_id', $documentId)
                    ->exists();
                if ($exists) {
                    $report->skippedOne('candidat_documents');
                    continue;
                }

                $extension = strtolower(pathinfo($srcPath, PATHINFO_EXTENSION) ?: 'bin');

                if (! $dryRun) {
                    CandidatDocument::query()->create([
                        'candidat_id'        => $candidatId,
                        'document_requis_id' => $documentId,
                        'file_path'          => ltrim(preg_replace('#^\.\./documentcupk/#', '', $srcPath) ?: $srcPath, '/'),
                        'disk'               => 'legacy',
                        'mime_type'          => $this->guessMime($extension),
                        'size_bytes'         => 0, // unknown without statting the file
                        'original_name'      => basename($srcPath),
                        'uploaded_at'        => now(),
                    ]);
                }
                $report->importedOne('candidat_documents');
            } catch (\Throwable $e) {
                $report->failedOne('candidat_documents', "{$legacyEtuId}/{$legacyDocId}", $e->getMessage());
            }
        }
    }

    /**
     * Builds legacy iddoc → new uuid map, by matching the code field.
     *
     * @return array<int, string>
     */
    private function mapLegacyDocumentCodes(LegacyDumpParser $parser, LegacyImportContext $context): array
    {
        if ($context->documentByLegacyId !== []) {
            return $context->documentByLegacyId;
        }
        $newByCode = DocumentRequis::query()->get(['id', 'code'])->keyBy('code');
        foreach ($parser->rowsOf('documents') as $row) {
            $code = (string) ($row['code'] ?? '');
            if ($newByCode->has($code)) {
                $context->documentByLegacyId[(int) ($row['iddoc'] ?? 0)] = (string) $newByCode[$code]->id;
            }
        }
        return $context->documentByLegacyId;
    }

    private function guessMime(string $ext): string
    {
        return match ($ext) {
            'pdf'  => 'application/pdf',
            'jpg', 'jpeg' => 'image/jpeg',
            'png'  => 'image/png',
            'webp' => 'image/webp',
            default => 'application/octet-stream',
        };
    }
}
