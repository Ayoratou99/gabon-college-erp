<?php

declare(strict_types=1);

namespace Modules\Concours\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Modules\Concours\Models\Candidat;

final class DossierRejectedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /** @param list<string> $motifs */
    public function __construct(
        public readonly Candidat $candidat,
        public readonly array $motifs,
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
            ->line("Votre dossier d'inscription au concours du CUK n'a pas pu être validé pour le motif suivant :");

        foreach ($this->motifs as $motif) {
            $message->line('• ' . $motif);
        }

        return $message
            ->line('Vous pouvez modifier votre dossier en saisissant votre email et téléphone sur la page suivante :')
            ->action('Modifier mon dossier', route('concours.public.lookup.form'))
            ->line("Matricule : **{$this->candidat->matricule_public}**");
    }
}
