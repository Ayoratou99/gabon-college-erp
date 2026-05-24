<?php

declare(strict_types=1);

namespace Modules\Concours\Services;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\Notification;
use InvalidArgumentException;
use Modules\Concours\DTOs\ValidationDecisionDto;
use Modules\Concours\Models\Candidat;
use Modules\Concours\Models\CandidatModification;
use Modules\Concours\Models\CandidatMotifRejet;
use Modules\Concours\Notifications\DossierAcceptedNotification;
use Modules\Concours\Notifications\DossierRejectedNotification;
use Modules\Parametrage\Services\SettingsService;

/**
 * Decision = accept (statut 'oui', awaiting payment) or reject (statut
 * 'rejete' with motifs). Both transitions are audited via the
 * candidat_modifications table and trigger an email notification.
 *
 * Notifications are queued; failed deliveries don't block the transaction.
 */
final class CandidatValidationService
{
    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly SettingsService $settings,
    ) {}

    public function decide(ValidationDecisionDto $dto): Candidat
    {
        $candidat = Candidat::query()->findOrFail($dto->candidatId);
        $oldStatut = $candidat->statut;

        $newStatut = match ($dto->decision) {
            ValidationDecisionDto::DECISION_ACCEPT => Candidat::STATUS_OUI,
            ValidationDecisionDto::DECISION_REJECT => Candidat::STATUS_REJETE,
            default => throw new InvalidArgumentException("Décision invalide : {$dto->decision}"),
        };

        if ($newStatut === Candidat::STATUS_REJETE && $dto->motifs === []) {
            throw new InvalidArgumentException('Au moins un motif est requis pour rejeter un dossier.');
        }

        $candidat = $this->db->transaction(function () use ($candidat, $newStatut, $dto, $oldStatut): Candidat {
            $candidat->forceFill([
                'statut'     => $newStatut,
                'valide_at'  => $newStatut === Candidat::STATUS_OUI ? now() : null,
                'rejete_at'  => $newStatut === Candidat::STATUS_REJETE ? now() : null,
            ])->save();

            if ($newStatut === Candidat::STATUS_REJETE) {
                foreach ($dto->motifs as $motif) {
                    CandidatMotifRejet::query()->create([
                        'candidat_id'        => $candidat->getKey(),
                        'motif'              => $motif,
                        'decided_by_user_id' => $dto->userId,
                        'decided_at'         => now(),
                    ]);
                }
            }

            CandidatModification::query()->create([
                'candidat_id' => $candidat->getKey(),
                'user_id'     => $dto->userId,
                'channel'     => CandidatModification::CHANNEL_ADMIN,
                'field'       => 'statut',
                'old_value'   => $oldStatut,
                'new_value'   => $newStatut,
                'reason'      => $dto->decision === ValidationDecisionDto::DECISION_REJECT
                    ? implode(' | ', $dto->motifs)
                    : null,
                'changed_at'  => now(),
            ]);

            return $candidat;
        });

        $this->notify($candidat, $newStatut, $dto->motifs);

        return $candidat;
    }

    /** @param list<string> $motifs */
    private function notify(Candidat $candidat, string $newStatut, array $motifs): void
    {
        if ($candidat->email === null || $candidat->email === '') {
            return; // legacy / phone-only candidat — no email channel
        }

        $notification = match ($newStatut) {
            Candidat::STATUS_OUI => new DossierAcceptedNotification(
                candidat: $candidat,
                feeAmount: (int) $this->settings->get('concours.fee.amount', 10300),
                currency: (string) $this->settings->get('concours.fee.currency', 'FCFA'),
            ),
            Candidat::STATUS_REJETE => new DossierRejectedNotification(
                candidat: $candidat,
                motifs: $motifs,
            ),
            default => null,
        };

        if ($notification !== null) {
            Notification::route('mail', $candidat->email)->notify($notification);
        }
    }
}
