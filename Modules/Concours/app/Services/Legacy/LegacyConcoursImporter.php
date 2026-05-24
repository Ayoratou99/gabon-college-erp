<?php

declare(strict_types=1);

namespace Modules\Concours\Services\Legacy;

use Modules\AcademicStructure\Models\AnneeAcademique;
use Modules\Concours\Models\ConcoursSession;
use Modules\UserManagement\Services\LegacyDumpParser;

/**
 * Maps legacy `concours` rows → new `concours_sessions`, keyed on legacy_id.
 *
 * The legacy schema linked concours to `annees`. We match the legacy `idan`
 * to a `code` (year string), then to our new `annees_academiques.code`
 * in the form "{prev}-{year}". If no matching année is found, we skip.
 */
final class LegacyConcoursImporter
{
    public function import(
        LegacyDumpParser $parser,
        LegacyImportContext $context,
        LegacyImportReport $report,
        bool $dryRun,
    ): void {
        $anneeCodeByLegacyId = $this->mapLegacyAnneeCodes($parser);

        foreach ($parser->rowsOf('concours') as $row) {
            $legacyId = (int) ($row['idconc'] ?? 0);
            if ($legacyId === 0) {
                $report->skippedOne('concours_sessions');
                continue;
            }

            try {
                $anneeCode = $anneeCodeByLegacyId[(int) ($row['idan'] ?? 0)] ?? null;
                if ($anneeCode === null) {
                    $report->failedOne('concours_sessions', (string) $legacyId, "Année légataire introuvable.");
                    continue;
                }

                // Legacy année code is "2025" → new format "2025-2026".
                $annee = AnneeAcademique::query()
                    ->where('code', "{$anneeCode}-" . ((int) $anneeCode + 1))
                    ->first()
                    ?? AnneeAcademique::query()->where('code', 'like', "{$anneeCode}%")->first();

                if ($annee === null) {
                    $report->failedOne('concours_sessions', (string) $legacyId,
                        "Aucune AnneeAcademique ne correspond à {$anneeCode}.");
                    continue;
                }

                $existing = ConcoursSession::query()->where('legacy_id', $legacyId)->first();
                if ($existing !== null) {
                    $context->sessionByLegacyId[$legacyId] = (string) $existing->id;
                    $report->skippedOne('concours_sessions');
                    continue;
                }

                $session = new ConcoursSession([
                    'annee_academique_id'         => $annee->id,
                    'code'                        => 'CONCOURS-LEGACY-' . $anneeCode,
                    'libelle'                     => 'Concours (legacy) — session ' . $anneeCode,
                    'date_ouverture_inscriptions' => $row['date_deb'] ?? "{$anneeCode}-01-01",
                    'date_fermeture_inscriptions' => $row['date_fin'] ?? "{$anneeCode}-12-31",
                    'date_concours'               => $row['date_conc'] ?? $row['date_fin'] ?? "{$anneeCode}-12-31",
                    'statut'                      => 'clos',
                    'est_active'                  => false,
                ]);
                $session->forceFill(['legacy_id' => $legacyId]);

                if (! $dryRun) {
                    $session->save();
                    $context->sessionByLegacyId[$legacyId] = (string) $session->id;
                }
                $report->importedOne('concours_sessions');
            } catch (\Throwable $e) {
                $report->failedOne('concours_sessions', (string) $legacyId, $e->getMessage());
            }
        }
    }

    /** @return array<int, string> idan → code */
    private function mapLegacyAnneeCodes(LegacyDumpParser $parser): array
    {
        $map = [];
        foreach ($parser->rowsOf('annees') as $row) {
            $map[(int) ($row['idan'] ?? 0)] = (string) ($row['code'] ?? '');
        }
        return $map;
    }
}
