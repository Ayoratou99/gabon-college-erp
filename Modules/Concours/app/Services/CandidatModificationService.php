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
                // Snapshot the originals BEFORE save() — Eloquent's save()
                // calls syncOriginal() which overwrites the pre-edit
                // snapshot, so we'd otherwise log `old_value === new_value`.
                $originals = [];
                foreach (array_keys($changes) as $field) {
                    $originals[$field] = $candidat->getAttribute($field);
                }

                $candidat->forceFill($changes)->save();

                foreach ($changes as $field => $newValue) {
                    CandidatModification::query()->create([
                        'candidat_id' => $candidat->getKey(),
                        'user_id'     => $dto->userId,
                        'channel'     => $dto->channel,
                        'field'       => $field,
                        'old_value'   => $this->stringify($originals[$field] ?? null),
                        'new_value'   => $this->stringify($newValue),
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
            if ($this->valuesEqual($candidat, $field, $newValue)) {
                continue;
            }
            $changes[$field] = $newValue;
        }
        return $changes;
    }

    /**
     * Type-aware equality check. The cheap `(string) $a === (string) $b`
     * trick falsely flags every Carbon date and every boolean as "changed":
     *
     *   - `date_naissance` is cast to Carbon → `(string) $c->date_naissance`
     *     yields "2000-01-01 00:00:00" but the form posts "2000-01-01".
     *   - `deja_bac` is cast to bool → `(string) false` is "", form posts "0".
     *
     * Without this check, re-saving an unchanged dossier writes an audit row
     * per field — noisy and misleading. We normalise per cast type.
     */
    private function valuesEqual(Candidat $candidat, string $field, mixed $newValue): bool
    {
        $current = $candidat->getAttribute($field);

        // date casts → compare Y-m-d only.
        if ($current instanceof \DateTimeInterface) {
            return $current->format('Y-m-d') === (string) (
                $newValue instanceof \DateTimeInterface
                    ? $newValue->format('Y-m-d')
                    : substr((string) $newValue, 0, 10)
            );
        }

        // boolean casts → normalise both sides through (bool) coercion.
        if (is_bool($current) || $field === 'deja_bac') {
            return (bool) $current === (bool) $newValue;
        }

        // numeric IDs / integer years.
        if (is_int($current) || $field === 'annee_bac') {
            return (int) $current === (int) $newValue;
        }

        // Default: trim + string compare. Empty string ≡ null.
        $a = trim((string) ($current ?? ''));
        $b = trim((string) $newValue);
        return $a === $b;
    }

    private function snakeToCamel(string $value): string
    {
        return lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $value))));
    }

    /**
     * Human-friendly stringification for the audit row's old/new values.
     * Dates collapse to Y-m-d; booleans become "oui"/"non"; null becomes
     * the empty string. Anything else passes through `(string)`.
     */
    private function stringify(mixed $value): string
    {
        if ($value === null) {
            return '';
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }
        if (is_bool($value)) {
            return $value ? 'oui' : 'non';
        }
        return (string) $value;
    }
}
