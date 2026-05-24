<?php

declare(strict_types=1);

namespace Modules\Concours\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Modules\Concours\Models\Candidat;

/**
 * Sent when an admin accepts a dossier (statut → 'oui').
 *
 * Queued because the email goes through SMTP and we don't want the admin's
 * "accept" click to wait on the mail server.
 */
final class DossierAcceptedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Candidat $candidat,
        public readonly int $feeAmount,
        public readonly string $currency,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = route('concours.public.candidat.dashboard', $this->candidat->matricule_public);

        return (new MailMessage())
            ->subject('Votre dossier d\'inscription est accepté')
            ->greeting("Bonjour {$this->candidat->prenom},")
            ->line("Bonne nouvelle : votre dossier d'inscription au concours du Centre Universitaire de Koulamoutou a été **accepté**.")
            ->line("Pour finaliser votre inscription, veuillez procéder au paiement des frais de "
                . number_format($this->feeAmount, 0, ',', ' ') . " {$this->currency}.")
            ->action('Accéder à mon dossier', $url)
            ->line("Votre matricule : **{$this->candidat->matricule_public}**")
            ->line('Conservez cet email pour vos prochaines vérifications.');
    }
}
