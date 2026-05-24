<?php

declare(strict_types=1);

namespace Modules\Concours\Services\Legacy;

use Illuminate\Support\Str;
use Modules\AcademicStructure\Models\Section;
use Modules\Concours\Models\Candidat;
use Modules\Concours\Models\Centre;
use Modules\Concours\Models\ConcoursSession;
use Modules\Referentiels\Models\Nationalite;
use Modules\Referentiels\Models\SerieBac;
use Modules\UserManagement\Models\User;
use Modules\UserManagement\Services\LegacyDumpParser;

/**
 * Imports the historical `etudiants` table → new `candidats`.
 *
 * Resolution map (legacy ID → new UUID), built before the loop:
 *   centres        : matched by lowercase nom
 *   sections       : matched by code (CI, AEC, IC, MEB, …)
 *   series_bac     : matched by code (C, D, SI, …)
 *   nationalites   : matched by exact French nom (case-folded)
 *
 * The `valid` column in legacy maps 1:1 to our STATUS_* constants.
 * Missing emails (very common in legacy data — registration form combined
 * email+tel into one field) are kept as NULL; the partial unique index
 * allows multiple nulls per session.
 *
 * Idempotent: a re-run skips any row whose `legacy_id` already exists.
 */
final class LegacyCandidatImporter
{
    public function import(
        LegacyDumpParser $parser,
        LegacyImportContext $context,
        LegacyImportReport $report,
        bool $dryRun,
    ): void {
        $this->buildReferentialMaps($parser, $context);

        $defaultSession = ConcoursSession::query()
            ->whereNotNull('legacy_id')
            ->orderByDesc('legacy_id')
            ->first();

        foreach ($parser->rowsOf('etudiants') as $row) {
            $legacyId = (int) ($row['idetu'] ?? 0);
            if ($legacyId === 0) {
                $report->skippedOne('candidats');
                continue;
            }

            try {
                $existing = Candidat::query()->where('legacy_id', $legacyId)->first();
                if ($existing !== null) {
                    $context->candidatByLegacyId[$legacyId] = (string) $existing->id;
                    $report->skippedOne('candidats');
                    continue;
                }

                $session = $this->resolveSession($context, (int) ($row['idan'] ?? 0)) ?? $defaultSession;
                if ($session === null) {
                    $report->failedOne('candidats', (string) $legacyId, 'Aucune ConcoursSession cible.');
                    continue;
                }

                $centreId  = $context->centreByLegacyId[(int) ($row['idcent'] ?? 0)] ?? null;
                $sectionId = $context->sectionByLegacyId[(int) ($row['idsect'] ?? 0)] ?? null;
                $bacId     = $context->serieBacByLegacyId[(int) ($row['idbac'] ?? 0)] ?? null;
                $natId     = $this->resolveNationalite($context, (string) ($row['nationalite'] ?? ''));

                if ($centreId === null || $sectionId === null || $bacId === null || $natId === null) {
                    $report->failedOne('candidats', (string) $legacyId,
                        sprintf('FK manquante (centre=%s, section=%s, bac=%s, nat=%s).',
                            $centreId ?? 'null', $sectionId ?? 'null', $bacId ?? 'null', $natId ?? 'null',
                        ));
                    continue;
                }

                $candidat = new Candidat([
                    'concours_session_id'      => $session->id,
                    'centre_id'                => $centreId,
                    'user_id'                  => $this->resolveUserId($row),
                    'nom'                      => mb_strtoupper(trim((string) ($row['nom'] ?? ''))),
                    'prenom'                   => $this->ucWords(trim((string) ($row['prenom'] ?? ''))),
                    'date_naissance'           => $row['dtnais'] ?? '1970-01-01',
                    'lieu_naissance'           => trim((string) ($row['lieunais'] ?? 'Inconnu')),
                    'sexe'                     => in_array($row['sexe'] ?? null, ['M', 'F'], true) ? $row['sexe'] : 'M',
                    'nationalite_id'           => $natId,
                    'email'                    => $this->normaliseEmail($row['email'] ?? null),
                    'telephone'                => $this->normalisePhone((string) ($row['tel'] ?? '')),
                    'deja_bac'                 => $row['dejabac'] !== null,
                    'annee_bac'                => $row['annebac'] !== null ? (int) $row['annebac'] : null,
                    'serie_bac_id'             => $bacId,
                    'bac_libelle_libre'        => $row['bac_name'] ?: null,
                    'etablissement_frequente'  => trim((string) ($row['eta_fre'] ?? 'Non précisé')),
                    'section_premier_choix_id' => $sectionId,
                    'section_second_choix_id'  => $context->sectionByLegacyId[(int) ($row['idsect2'] ?? 0)] ?? null,
                    'statut'                   => $this->mapStatut((string) ($row['valid'] ?? 'non')),
                    'matricule_public'         => $this->generateMatricule(),
                ]);
                $candidat->forceFill(['legacy_id' => $legacyId]);

                if (! $dryRun) {
                    $candidat->save();
                    $context->candidatByLegacyId[$legacyId] = (string) $candidat->id;
                }
                $report->importedOne('candidats');
            } catch (\Throwable $e) {
                $report->failedOne('candidats', (string) $legacyId, $e->getMessage());
            }
        }
    }

    private function buildReferentialMaps(LegacyDumpParser $parser, LegacyImportContext $context): void
    {
        // centres: legacy nom → new id (we matched by name during seeding,
        // so we re-match the same way here for consistency).
        $newCentresByLowerNom = Centre::query()->get(['id', 'nom'])
            ->keyBy(fn ($c) => mb_strtolower(trim($c->nom)));

        foreach ($parser->rowsOf('centres') as $row) {
            $key = mb_strtolower(trim((string) ($row['nom'] ?? '')));
            if ($key !== '' && $newCentresByLowerNom->has($key)) {
                $context->centreByLegacyId[(int) ($row['idcent'] ?? 0)] = (string) $newCentresByLowerNom[$key]->id;
            }
        }

        $newSectionsByCode = Section::query()->get(['id', 'code'])->keyBy('code');
        foreach ($parser->rowsOf('sections') as $row) {
            $code = (string) ($row['code'] ?? '');
            if ($newSectionsByCode->has($code)) {
                $context->sectionByLegacyId[(int) ($row['idsect'] ?? 0)] = (string) $newSectionsByCode[$code]->id;
            }
        }

        $newBacByCode = SerieBac::query()->get(['id', 'code'])->keyBy('code');
        foreach ($parser->rowsOf('series_bac') as $row) {
            $code = (string) ($row['code'] ?? '');
            if ($newBacByCode->has($code)) {
                $context->serieBacByLegacyId[(int) ($row['idbac'] ?? 0)] = (string) $newBacByCode[$code]->id;
            }
        }

        // nationalites: legacy stored a string "Gabonais" in candidat rows
        // (not an FK), so map by lowercased nom.
        $context->nationaliteByLegacyName = Nationalite::query()->get(['id', 'nom'])
            ->mapWithKeys(fn ($n) => [mb_strtolower(trim($n->nom)) => (string) $n->id])
            ->all();
    }

    private function resolveSession(LegacyImportContext $context, int $idan): ?ConcoursSession
    {
        // The candidat row points to idan, not idconc. We match the session
        // whose legacy_id maps back via the LegacyConcoursImporter.
        foreach ($context->sessionByLegacyId as $sessionUuid) {
            $s = ConcoursSession::query()->find($sessionUuid);
            if ($s !== null && (int) $s->legacy_id === $idan) {
                return $s;
            }
        }
        return ConcoursSession::query()->whereNotNull('legacy_id')->orderByDesc('legacy_id')->first();
    }

    private function resolveNationalite(LegacyImportContext $context, string $rawName): ?string
    {
        $key = mb_strtolower(trim($rawName));
        if ($key === '') {
            return $context->nationaliteByLegacyName['gabonais'] ?? null;
        }
        return $context->nationaliteByLegacyName[$key]
            ?? $context->nationaliteByLegacyName['gabonais']
            ?? null;
    }

    private function resolveUserId(array $row): ?string
    {
        // Legacy candidats never had user accounts. The only way they get
        // one is via Stage 5B SelectionService — so on import, leave null.
        return null;
    }

    private function mapStatut(string $legacy): string
    {
        return match ($legacy) {
            'oui'    => Candidat::STATUS_OUI,
            'valid'  => Candidat::STATUS_VALID,
            'rejete' => Candidat::STATUS_REJETE,
            'admis'  => Candidat::STATUS_ADMIS,
            default  => Candidat::STATUS_NON,
        };
    }

    private function normaliseEmail(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }
        $trimmed = trim($raw);
        if ($trimmed === '' || ! str_contains($trimmed, '@')) {
            return null;
        }
        return mb_strtolower($trimmed);
    }

    private function normalisePhone(string $raw): string
    {
        $stripped = preg_replace('/\s+/', '', trim($raw)) ?? $raw;
        return mb_substr($stripped, 0, 30);
    }

    private function ucWords(string $value): string
    {
        return mb_convert_case($value, MB_CASE_TITLE, 'UTF-8');
    }

    private function generateMatricule(): string
    {
        do {
            $m = 'CUK-' . Str::upper(Str::random(12));
        } while (Candidat::query()->where('matricule_public', $m)->exists());
        return $m;
    }
}
