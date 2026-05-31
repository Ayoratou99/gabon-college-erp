<?php

declare(strict_types=1);

namespace Modules\Concours\Services\Legacy;

use Modules\Concours\Models\CandidatMotifRejet;
use Modules\UserManagement\Services\LegacyDumpParser;

/**
 * Imports `motifs` → `candidat_motifs_rejet`.
 *
 * The legacy table had no PK and no `decided_by` column — `decided_by_user_id`
 * is set to null in the new row. The cutover preserves the motif text verbatim.
 */
final class LegacyMotifImporter
{
    public function import(
        LegacyDumpParser $parser,
        LegacyImportContext $context,
        LegacyImportReport $report,
        bool $dryRun,
    ): void {
        foreach ($parser->rowsOf('motifs') as $row) {
            $legacyEtuId = (int) ($row['idetu'] ?? 0);
            $motif       = trim((string) ($row['motif'] ?? ''));

            if ($legacyEtuId === 0 || $motif === '') {
                $report->skippedOne('candidat_motifs_rejet');
                continue;
            }

            try {
                $candidatId = $context->candidatByLegacyId[$legacyEtuId] ?? null;
                if ($candidatId === null) {
                    // Orphan: the legacy candidat row no longer exists at all
                    // in the source dump (was physically deleted) — there's
                    // nothing to attach this motif to. Quietly skip rather
                    // than reporting as an error.
                    if (! isset($context->legacyEtudiantIds[$legacyEtuId])) {
                        $report->skippedOne('candidat_motifs_rejet');
                    } else {
                        $report->failedOne('candidat_motifs_rejet', (string) $legacyEtuId, 'Candidat non importé.');
                    }
                    continue;
                }

                $exists = CandidatMotifRejet::query()
                    ->where('candidat_id', $candidatId)
                    ->where('motif', $motif)
                    ->exists();
                if ($exists) {
                    $report->skippedOne('candidat_motifs_rejet');
                    continue;
                }

                if (! $dryRun) {
                    CandidatMotifRejet::query()->create([
                        'candidat_id'        => $candidatId,
                        'motif'              => $motif,
                        'decided_by_user_id' => null,
                        'decided_at'         => now(),
                    ]);
                }
                $report->importedOne('candidat_motifs_rejet');
            } catch (\Throwable $e) {
                $report->failedOne('candidat_motifs_rejet', (string) $legacyEtuId, $e->getMessage());
            }
        }
    }
}
