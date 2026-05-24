<?php

declare(strict_types=1);

namespace Modules\Concours\Services;

use Illuminate\Database\ConnectionInterface;
use Modules\Concours\DTOs\UpdateCandidatDto;
use Modules\Concours\Models\Candidat;
use Modules\Concours\Models\CandidatModification;

/**
 * Applies field-level changes to a candidate while emitting a per-field
 * audit row for each change. Works for both back-office edits (chef-centre,
 * DE) and the public modification flow (after rejection).
 *
 * A public-channel update resets the statut to 'non' so the dossier
 * re-enters the validation queue.
 */
final class CandidatModificationService
{
    /** Fields that may be mutated through this service. */
    private const EDITABLE = [
        'nom', 'prenom', 'date_naissance', 'lieu_naissance', 'sexe',
        'nationalite_id', 'email', 'telephone',
        'deja_bac', 'annee_bac', 'serie_bac_id', 'bac_libelle_libre',
        'etablissement_frequente',
        'section_premier_choix_id', 'section_second_choix_id', 'centre_id',
    ];

    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly CandidatDocumentService $documents,
    ) {}

    public function apply(UpdateCandidatDto $dto): Candidat
    {
        $candidat = Candidat::query()->findOrFail($dto->candidatId);

        $changes = $this->diff($candidat, $dto);

        $this->db->transaction(function () use ($candidat, $dto, $changes): void {
            if ($changes !== []) {
                $candidat->forceFill($changes)->save();

                foreach ($changes as $field => $newValue) {
                    CandidatModification::query()->create([
                        'candidat_id' => $candidat->getKey(),
                        'user_id'     => $dto->userId,
                        'channel'     => $dto->channel,
                        'field'       => $field,
                        'old_value'   => (string) $candidat->getOriginal($field),
                        'new_value'   => (string) $newValue,
                        'reason'      => $dto->reason,
                        'ip_address'  => $dto->ipAddress,
                        'changed_at'  => now(),
                    ]);
                }
            }

            // Public modifications re-open the dossier.
            if ($dto->channel === CandidatModification::CHANNEL_PUBLIC) {
                $candidat->forceFill([
                    'statut'     => Candidat::STATUS_NON,
                    'rejete_at'  => null,
                ])->save();
            }
        });

        // Files (photo + documents) — outside the txn for the same reason as registration.
        $session = $candidat->session;
        $anneeCode = $session?->anneeAcademique?->code ?? date('Y');

        if ($dto->photo !== null) {
            $this->documents->storePhoto($candidat, $dto->photo, $anneeCode);
        }
        foreach ($dto->documents ?? [] as $code => $file) {
            $required = \Modules\Referentiels\Models\DocumentRequis::query()
                ->where('code', $code)
                ->first();
            if ($required !== null) {
                $this->documents->storeDocument($candidat, $required, $file, $anneeCode);
            }
        }

        return $candidat->fresh();
    }

    /** @return array<string, mixed> */
    private function diff(Candidat $candidat, UpdateCandidatDto $dto): array
    {
        $changes = [];
        foreach (self::EDITABLE as $field) {
            $dtoField = $this->snakeToCamel($field);
            $newValue = $dto->{$dtoField} ?? null;
            if ($newValue === null) {
                continue;
            }
            if ((string) $candidat->getAttribute($field) === (string) $newValue) {
                continue;
            }
            $changes[$field] = $newValue;
        }
        return $changes;
    }

    private function snakeToCamel(string $value): string
    {
        return lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $value))));
    }
}
