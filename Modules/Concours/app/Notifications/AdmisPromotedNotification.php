<?php

declare(strict_types=1);

namespace Modules\Concours\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Modules\Concours\Models\Candidat;
use Modules\UserManagement\Models\User;

/**
 * Sent right after CandidatPromotionService creates a User row for a
 * freshly-admis candidat. Walks them through the activation flow:
 *
 *   1. Open /connexion/premiere-fois
 *   2. Identify with email + telephone (the same ones they registered with)
 *   3. Set a password
 *   4. Set up 2FA (if their role requires it)
 *
 * The Candidat row is intentionally not referenced as the auth target —
 * it's the User row that backs the étudiant identity. The Candidat is
 * still queryable from inside the student space via
 * `User::promoted_from_candidat_id`.
 */
final class AdmisPromotedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Candidat $candidat,
        public readonly User $user,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $activationUrl = route('first-login.start');

        return (new MailMessage())
            ->subject('Félicitations — vous êtes admis(e) au concours CUK')
            ->greeting("Bonjour {$this->candidat->prenom},")
            ->line('Toutes nos félicitations&nbsp;: vous êtes **admis(e)** au concours du Centre Universitaire de Koulamoutou.')
            ->line('Votre matricule reste **' . $this->candidat->matricule_public . '**. '
                . 'Un compte étudiant a été créé pour vous — il vous permet d\'accéder à votre espace personnel, '
                . 'de télécharger votre attestation d\'admission et de suivre votre scolarité.')
            ->line('**Activation — comment se connecter pour la première fois&nbsp;:**')
            ->line('1. Ouvrez la page d\'activation ci-dessous.')
            ->line('2. Identifiez-vous avec **l\'email** et **le numéro de téléphone** que vous avez utilisés à l\'inscription.')
            ->line('3. Choisissez un mot de passe sécurisé (au moins 10 caractères).')
            ->line('4. Configurez l\'application d\'authentification (Google Authenticator, Authy, …) — cette double authentification protégera votre compte.')
            ->action('Activer mon compte étudiant', $activationUrl)
            ->line("Email d'activation&nbsp;: **{$this->user->email}**")
            ->line("Téléphone d'activation&nbsp;: **{$this->user->telephone}**")
            ->line('Si ces informations ne sont plus à jour, contactez le service scolarité — vous ne pourrez pas activer votre compte sans elles.')
            ->salutation('Bienvenue parmi nous, l\'équipe CUK');
    }
}
