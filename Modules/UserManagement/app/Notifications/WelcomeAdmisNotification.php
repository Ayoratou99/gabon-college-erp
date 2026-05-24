<?php

declare(strict_types=1);

namespace Modules\UserManagement\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Modules\UserManagement\Models\User;

/**
 * Sent once, immediately after a Candidat is converted to a User following
 * results publication. Carries the *plaintext* temporary password — the
 * service generating it passes it through here before discarding it.
 *
 * The user must change it on first login (enforced separately by a Stage 6
 * post-login hook, TBD).
 */
final class WelcomeAdmisNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly User $user,
        public readonly string $temporaryPassword,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $loginIdentifier = $this->user->email ?? $this->user->telephone;

        return (new MailMessage())
            ->subject('Votre compte étudiant CUK est prêt')
            ->greeting("Bienvenue {$this->user->prenom},")
            ->line('Votre admission au Centre Universitaire de Koulamoutou est confirmée et un compte d\'accès vous a été créé.')
            ->line('**Vos identifiants de connexion :**')
            ->line('• Identifiant : ' . $loginIdentifier)
            ->line('• Mot de passe temporaire : `' . $this->temporaryPassword . '`')
            ->action('Se connecter', route('login'))
            ->line('Vous serez invité(e) à modifier votre mot de passe lors de votre première connexion.')
            ->line('Pour des raisons de sécurité, **ne partagez jamais ce mot de passe**.');
    }
}
