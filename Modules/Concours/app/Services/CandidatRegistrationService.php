<?php

declare(strict_types=1);

namespace Modules\Concours\Services;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;
use Modules\AcademicStructure\Models\AnneeAcademique;
use Modules\Concours\DTOs\RegisterCandidatDto;
use Modules\Concours\Exceptions\InscriptionsClosedException;
use Modules\Concours\Models\Candidat;
use Modules\Concours\Models\ConcoursSession;
use Modules\Referentiels\Models\DocumentRequis;

/**
 * Orchestrates the public candidate registration pipeline:
 *
 *   1. Verify the session is open to inscriptions today.
 *   2. Create the Candidat row (status = 'non') in a transaction.
 *   3. Store the photo + the declared document files keyed by code.
 *   4. Return the persisted Candidat (with matricule_public set).
 *
 * The matricule is short, urlsafe, and unique — used in the verification
 * URL the candidate gets at the end of the flow.
 */
final class CandidatRegistrationService
{
    public function __construct(
        private readonly CandidatDocumentService $documents,
        private readonly ConnectionInterface $db,
    ) {}

    public function register(RegisterCandidatDto $dto): Candidat
    {
        $session = ConcoursSession::query()->findOrFail($dto->concoursSessionId);

        if (! $session->isInscriptionOpen()) {
            throw InscriptionsClosedException::outsideDateRange();
        }

        $documentRequisByCode = DocumentRequis::query()
            ->where('active', true)
            ->whereIn('code', array_keys($dto->documents))
            ->get()
            ->keyBy('code');

        $candidat = $this->db->transaction(function () use ($dto, $session): Candidat {
            return Candidat::query()->create([
                'concours_session_id'      => $session->getKey(),
                'centre_id'                => $dto->centreId,
                'nom'                      => mb_strtoupper(trim($dto->nom)),
                'prenom'                   => $this->ucWords(trim($dto->prenom)),
                'date_naissance'           => $dto->dateNaissance,
                'lieu_naissance'           => trim($dto->lieuNaissance),
                'sexe'                     => $dto->sexe,
                'nationalite_id'           => $dto->nationaliteId,
                'email'                    => mb_strtolower(trim($dto->email)),
                'telephone'                => $this->normalizePhone($dto->telephone),
                'deja_bac'                 => $dto->dejaBac,
                'annee_bac'                => $dto->dejaBac ? $dto->anneeBac : null,
                'serie_bac_id'             => $dto->serieBacId,
                'bac_libelle_libre'        => $dto->bacLibelleLibre,
                'etablissement_frequente'  => trim($dto->etablissementFrequente),
                'section_premier_choix_id' => $dto->sectionPremierChoixId,
                'section_second_choix_id'  => $dto->sectionSecondChoixId,
                'statut'                   => Candidat::STATUS_NON,
                'matricule_public'         => $this->generateMatricule(),
            ]);
        });

        // File storage happens OUTSIDE the transaction — Postgres can't roll
        // back a file write, and we'd rather have an orphan file than a row
        // referencing a non-existent path.
        $anneeCode = $session->anneeAcademique?->code ?? date('Y');

        $this->documents->storePhoto($candidat, $dto->photo, $anneeCode);

        foreach ($dto->documents as $code => $file) {
            $required = $documentRequisByCode->get($code);
            if ($required === null) {
                continue; // unknown code → silently ignore (validation layer should have caught it)
            }
            $this->documents->storeDocument($candidat, $required, $file, $anneeCode);
        }

        return $candidat->fresh();
    }

    private function generateMatricule(): string
    {
        // 12 alphanumeric chars, prefix "CUK-" → "CUK-7K2R9P4A8M1F" (16 total)
        do {
            $candidate = 'CUK-' . Str::upper(Str::random(12));
        } while (Candidat::query()->where('matricule_public', $candidate)->exists());

        return $candidate;
    }

    private function normalizePhone(string $raw): string
    {
        $trimmed = preg_replace('/\s+/', '', trim($raw)) ?? $raw;
        return mb_substr($trimmed, 0, 30);
    }

    private function ucWords(string $value): string
    {
        return mb_convert_case($value, MB_CASE_TITLE, 'UTF-8');
    }
}
