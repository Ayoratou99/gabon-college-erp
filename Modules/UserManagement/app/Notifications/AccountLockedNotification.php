<?php

declare(strict_types=1);

namespace Modules\UserManagement\Notifications;

use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Modules\UserManagement\Models\User;

/**
 * Sent when the slow throttle tier triggers on a known account — typically
 * 5 failed attempts in 24h. Signals possible credential probing.
 */
final class AccountLockedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly User $user,
        public readonly string $ipAddress,
        public readonly Carbon $unlocksAt,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject('Alerte sécurité — Trop de tentatives de connexion')
            ->greeting("Bonjour {$this->user->prenom},")
            ->line("Plusieurs tentatives de connexion infructueuses ont été détectées sur votre compte depuis l'adresse IP {$this->ipAddress}.")
            ->line('Par mesure de sécurité, votre compte est temporairement verrouillé.')
            ->line('Déblocage prévu : ' . $this->unlocksAt->format('d/m/Y à H:i'))
            ->line('Si vous n\'êtes pas à l\'origine de ces tentatives, contactez immédiatement le support.')
            ->action('Se connecter', route('login'));
    }
}
