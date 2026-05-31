<?php

declare(strict_types=1);

namespace Modules\Concours\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Modules\Concours\Models\Candidat;

/**
 * Sent when an admin rejects a dossier (statut → 'rejete').
 *
 * Two distinct rejection layers — both surfaced in the email:
 *
 *   1. Global motifs (`$motifs`)  — the mandatory reasons captured at the
 *      moment of rejection. CandidatValidationService requires at least
 *      one. These describe dossier-level issues that don't necessarily
 *      map to a specific document (wrong age, missing centre choice,
 *      duplicate email, etc.).
 *
 *   2. Per-document rejections (`$rejectedDocuments`) — optional, additive.
 *      Whatever specific files chef-centre marked `a_refaire` in the
 *      review workflow. Each entry is `['libelle' => string, 'comment'
 *      => ?string]`. Pass an empty array when no docs were flagged.
 */
final class DossierRejectedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param list<string> $motifs                Global dossier-level motifs (>=1).
     * @param list<array{libelle: string, comment: ?string}> $rejectedDocuments  Optional per-doc detail.
     */
    public function __construct(
        public readonly Candidat $candidat,
        public readonly array $motifs,
        public readonly array $rejectedDocuments = [],
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage())
            ->subject('Votre dossier d\'inscription doit être complété')
            ->greeting("Bonjour {$this->candidat->prenom},")
            ->line("Votre dossier d'inscription au concours du CUK n'a pas pu être validé pour le(s) motif(s) suivant(s)&nbsp;:");

        foreach ($this->motifs as $motif) {
            $message->line('• ' . $motif);
        }

        // Per-doc layer — only when there's something to say. Renders as a
        // separate sub-section so it's clear it's additive to the motifs
        // above, not a replacement.
        if ($this->rejectedDocuments !== []) {
            $message->line(' ')
                ->line('**Pièces justificatives à reprendre&nbsp;:**');

            foreach ($this->rejectedDocuments as $doc) {
                $line = '• ' . $doc['libelle'];
                if (! empty($doc['comment'])) {
                    $line .= ' — _' . $doc['comment'] . '_';
                }
                $message->line($line);
            }
        }

        return $message
            ->line('Vous pouvez modifier votre dossier en saisissant votre email et téléphone sur la page suivante. Les pièces marquées « à refaire » vous seront pré-signalées dans le formulaire.')
            ->action('Modifier mon dossier', route('concours.public.lookup.form'))
            ->line("Matricule&nbsp;: **{$this->candidat->matricule_public}**");
    }
}
