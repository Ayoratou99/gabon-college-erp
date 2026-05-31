<?php

declare(strict_types=1);

namespace Modules\Concours\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Modules\Concours\Models\Candidat;

/**
 * Sent immediately after a public inscription succeeds. Confirms the
 * matricule, sets expectations on the review timeline, and gives the
 * candidat a working URL back into their dossier.
 *
 * Queued — the registration controller has just done file I/O and a DB
 * write; we don't want the candidat's "submit" click to also wait on SMTP.
 */
final class InscriptionConfirmedNotification extends Notification implements ShouldQueue
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
        $dashboardUrl = route('concours.public.candidat.dashboard', $this->candidat->matricule_public);
        $statusUrl    = route('concours.public.status.form');
        $session      = $this->candidat->session;

        return (new MailMessage())
            ->subject('Inscription au concours CUK — votre matricule')
            ->greeting("Bonjour {$this->candidat->prenom},")
            ->line('Votre dossier a bien été enregistré pour le **'
                . ($session?->libelle ?? 'concours CUK') . '**.')
            ->line('**Votre matricule : ' . $this->candidat->matricule_public . '**')
            ->line('Cet identifiant vous suivra tout au long du processus — notez-le ou imprimez ce courriel.')
            ->line('Notre équipe va examiner votre dossier dans les **3 à 5 jours ouvrés**. '
                . 'Vous recevrez un email dès qu\'une décision aura été prise.')
            ->line('Si votre dossier est accepté, vous serez invité(e) à payer les frais '
                . 'd\'inscription de **' . number_format($this->feeAmount, 0, ',', ' ') . ' '
                . $this->currency . '** via eBilling.')
            ->action('Suivre mon dossier', $dashboardUrl)
            ->line('Vous pouvez à tout moment vérifier le statut de votre demande sur :')
            ->line($statusUrl)
            ->salutation('Cordialement, l\'équipe Concours CUK');
    }
}
