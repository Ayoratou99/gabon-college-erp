<?php

declare(strict_types=1);

namespace Modules\Concours\Services;

use Illuminate\Database\ConnectionInterface;
use InvalidArgumentException;
use Modules\Concours\DTOs\SaveNotesBatchDto;
use Modules\Concours\Exceptions\EpreuveNotApplicableException;
use Modules\Concours\Models\Candidat;
use Modules\Concours\Models\Epreuve;
use Modules\Concours\Models\Note;

/**
 * Bulk-saves notes for one epreuve. Validates that every candidat_id in the
 * batch is actually eligible for the epreuve (defence: a malicious admin
 * could otherwise inject a note for an unrelated candidate).
 *
 * Locking semantics:
 *   - chef-centre sets locked=true on a batch when they're done with that
 *     epreuve. Further updates require DE/DG (enforced in the controller
 *     via RBAC).
 */
final class NoteService
{
    public function __construct(
        private readonly ConnectionInterface $db,
    ) {}

    public function saveBatch(SaveNotesBatchDto $dto): int
    {
        $epreuve = Epreuve::query()->findOrFail($dto->epreuveId);

        // Build the set of candidat_ids that *can* take this epreuve.
        $eligibleIds = $epreuve->eligibleCandidatsQuery()->pluck('id')->all();
        $eligibleSet = array_flip($eligibleIds);

        $count = 0;
        $this->db->transaction(function () use ($dto, $epreuve, $eligibleSet, &$count): void {
            foreach ($dto->entries as $entry) {
                $candidatId = (string) $entry['candidat_id'];
                if (! isset($eligibleSet[$candidatId])) {
                    throw EpreuveNotApplicableException::for($epreuve->getKey(), $candidatId);
                }
                $valeur = $entry['valeur'] ?? null;
                $absent = (bool) ($entry['absent'] ?? false);

                if ($valeur !== null) {
                    if ($valeur < 0 || $valeur > (float) $epreuve->note_max) {
                        throw new InvalidArgumentException(sprintf(
                            'Note %s hors plage [0, %s] pour le candidat %s.',
                            $valeur, $epreuve->note_max, $candidatId,
                        ));
                    }
                    if ($absent) {
                        throw new InvalidArgumentException("Un candidat absent ne peut pas avoir une note ({$candidatId}).");
                    }
                }

                Note::query()->updateOrCreate(
                    ['candidat_id' => $candidatId, 'epreuve_id' => $epreuve->getKey()],
                    [
                        'valeur'              => $valeur,
                        'absent'              => $absent,
                        'locked'              => $dto->lock,
                        'entered_by_user_id'  => $dto->userId,
                        'entered_at'          => now(),
                        'updated_by_user_id'  => $dto->userId,
                        'commentaire'         => $entry['commentaire'] ?? null,
                    ],
                );
                $count++;
            }
        });

        return $count;
    }

    public function unlock(Note $note): void
    {
        $note->forceFill(['locked' => false])->save();
    }
}
