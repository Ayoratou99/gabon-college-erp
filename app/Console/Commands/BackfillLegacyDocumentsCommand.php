<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Modules\Concours\Models\Candidat;
use Modules\Concours\Models\CandidatDocument;
use Modules\Referentiels\Models\DocumentRequis;

/**
 * Backfill candidat_documents rows for legacy candidats whose files exist on
 * disk (documentcupk/) but were never linked by the dump-based import.
 *
 * The legacy importer (LegacyDocumentImporter) only created rows for entries
 * present in the old `documents_etudiants` table. Where that table was
 * incomplete, the physical files still sit in the documentcupk folder named
 * «{year}user{idetu}{code}.{ext}» (e.g. 2025user1369acte.pdf) but no
 * candidat_documents row points at them — so the back-office shows "0 pièce(s)"
 * even though the PDFs are right there.
 *
 * This command scans the `legacy` disk per candidat (keyed on legacy_id) and
 * creates the missing rows so the existing preview / count / fiche pipeline
 * surfaces them. No re-import, no schema change. Dry-run by default.
 *
 *   php artisan concours:backfill-legacy-documents
 *   php artisan concours:backfill-legacy-documents --matricule=CUK-BQDEHLMZA8TI --apply
 *   php artisan concours:backfill-legacy-documents --apply
 */
final class BackfillLegacyDocumentsCommand extends Command
{
    protected $signature = 'concours:backfill-legacy-documents
        {--apply : Persist the rows (default is a dry-run preview)}
        {--matricule= : Restrict to a single candidat by public matricule}';

    protected $description = 'Link on-disk legacy documents (documentcupk/) to candidats missing their candidat_documents rows.';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $disk  = Storage::disk('legacy');
        $root  = rtrim((string) $disk->path(''), '/\\');

        if ($root === '' || ! is_dir($root)) {
            $this->error("Le disque 'legacy' ne pointe pas vers un dossier existant : {$root}");
            $this->line('Vérifiez LEGACY_DOCUMENTS_PATH dans .env, puis « php artisan config:clear ».');

            return self::FAILURE;
        }

        /** @var \Illuminate\Support\Collection<string, DocumentRequis> $docsByCode */
        $docsByCode = DocumentRequis::query()
            ->where('active', true)
            ->get(['id', 'code', 'libelle'])
            ->keyBy('code');

        if ($docsByCode->isEmpty()) {
            $this->error('Aucun document requis actif — rien à mapper.');

            return self::FAILURE;
        }

        $query = Candidat::query()->whereNotNull('legacy_id');
        if ($mat = $this->option('matricule')) {
            $query->where('matricule_public', $mat);
        }

        $created = 0;
        $skipped = 0;
        $candidatsTouched = 0;
        $rows = [];

        $query->chunkById(200, function ($candidats) use (
            $disk, $root, $docsByCode, $apply, &$created, &$skipped, &$candidatsTouched, &$rows
        ): void {
            foreach ($candidats as $candidat) {
                $idetu = (int) $candidat->legacy_id;
                if ($idetu <= 0) {
                    continue;
                }

                // Gather this candidat's files. Glob is broad ("…user1369…"), so
                // the regex re-anchors: the CODE is the alphabetic run that must
                // immediately follow the idetu — that's what stops 1369 from
                // matching 13690 / 21369, and skips code-less photo names.
                $found = [];
                foreach (["*user{$idetu}*", "*user_{$idetu}*"] as $glob) {
                    foreach (glob($root . DIRECTORY_SEPARATOR . $glob, GLOB_NOSORT) ?: [] as $abs) {
                        $name = basename($abs);
                        if (preg_match('/user_?' . $idetu . '([a-z]+)\.([a-z0-9]+)$/i', $name, $m)) {
                            $code = mb_strtolower($m[1]);
                            $found[$code] ??= ['name' => $name, 'ext' => mb_strtolower($m[2])];
                        }
                    }
                }

                $touched = false;
                foreach ($found as $code => $meta) {
                    $requis = $docsByCode->get($code);
                    if ($requis === null) {
                        continue; // a file whose code isn't a known DocumentRequis
                    }

                    // Skip if already linked (live OR soft-deleted) to avoid a
                    // duplicate / unique-constraint hit.
                    $alreadyLinked = CandidatDocument::withTrashed()
                        ->where('candidat_id', $candidat->id)
                        ->where('document_requis_id', $requis->id)
                        ->exists();
                    if ($alreadyLinked) {
                        $skipped++;
                        continue;
                    }

                    $size = 0;
                    try {
                        $size = (int) $disk->size($meta['name']);
                    } catch (\Throwable) {
                        // leave 0 — effectiveSizeBytes() re-stats lazily on read
                    }

                    if ($apply) {
                        CandidatDocument::query()->create([
                            'candidat_id'        => $candidat->id,
                            'document_requis_id' => $requis->id,
                            'file_path'          => $meta['name'],
                            'disk'               => 'legacy',
                            'mime_type'          => $this->mimeFor($meta['ext']),
                            'size_bytes'         => $size,
                            'original_name'      => $meta['name'],
                            'uploaded_at'        => now(),
                        ]);
                    }

                    $created++;
                    $touched = true;
                    if (count($rows) < 40) {
                        $rows[] = [$candidat->matricule_public, $code, $meta['name'], $size];
                    }
                }

                if ($touched) {
                    $candidatsTouched++;
                }
            }
        });

        if ($rows !== []) {
            $this->table(['Matricule', 'Code', 'Fichier', 'Octets'], $rows);
            if ($created > count($rows)) {
                $this->line('… (' . ($created - count($rows)) . ' de plus, non affichées)');
            }
        }

        $verb = $apply ? 'créées' : 'à créer';
        $this->info("Documents legacy {$verb} : {$created}  ·  déjà liés (ignorés) : {$skipped}  ·  candidats concernés : {$candidatsTouched}");
        if (! $apply) {
            $this->warn('DRY-RUN — relancez avec --apply pour écrire les lignes.');
        }

        return self::SUCCESS;
    }

    private function mimeFor(string $ext): string
    {
        return match ($ext) {
            'pdf'         => 'application/pdf',
            'jpg', 'jpeg' => 'image/jpeg',
            'png'         => 'image/png',
            'webp'        => 'image/webp',
            'gif'         => 'image/gif',
            default       => 'application/octet-stream',
        };
    }
}
