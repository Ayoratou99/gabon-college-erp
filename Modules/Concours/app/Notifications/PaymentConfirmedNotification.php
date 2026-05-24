<?php

declare(strict_types=1);

namespace Modules\Concours\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Modules\Concours\Models\Candidat;
use Modules\Concours\Models\Payment;

final class PaymentConfirmedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Candidat $candidat,
        public readonly Payment $payment,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject('Paiement confirmé — Votre inscription est validée')
            ->greeting("Bonjour {$this->candidat->prenom},")
            ->line('Nous avons bien reçu votre paiement.')
            ->line('**Détails du paiement**')
            ->line('• Montant : ' . number_format($this->payment->amount, 0, ',', ' ') . ' ' . $this->payment->currency)
            ->line('• Référence : ' . $this->payment->external_reference)
            ->line('• Date : ' . $this->payment->paid_at?->format('d/m/Y à H:i'))
            ->line('Votre inscription est désormais **définitivement validée**. Vous serez convoqué(e) à l\'épreuve via cet email.')
            ->action('Voir mon dossier', route('concours.public.candidat.dashboard', $this->candidat->matricule_public))
            ->line("Matricule : **{$this->candidat->matricule_public}**");
    }
}
