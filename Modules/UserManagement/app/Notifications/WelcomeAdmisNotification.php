<?php

declare(strict_types=1);

namespace Modules\UserManagement\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Modules\UserManagement\Models\User;

/**
 * Sent once, right after an admis candidat is converted to a User during
 * results publication. Tells the student to activate their account through
 * the first-login wizard using the email + telephone they provided at
 * registration — we never email a password.
 */
final class WelcomeAdmisNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly User $user,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject('Bienvenue au CUK — activez votre compte étudiant')
            ->greeting("Félicitations {$this->user->prenom},")
            ->line('Votre admission au Centre Universitaire de Koulamoutou est confirmée et un compte étudiant a été créé à votre nom.')
            ->line('Pour activer votre accès, lancez la *première connexion* avec les informations que vous avez utilisées lors de votre inscription au concours :')
            ->line('• votre adresse email')
            ->line('• votre numéro de téléphone')
            ->line('Vous choisirez ensuite votre mot de passe et activerez la double authentification.')
            ->action('Activer mon compte', route('first-login.start'))
            ->line('Pour des raisons de sécurité, votre compte n\'a aucun mot de passe avant cette activation.');
    }
}
