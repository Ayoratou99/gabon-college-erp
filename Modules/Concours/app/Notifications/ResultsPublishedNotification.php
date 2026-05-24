<?php

declare(strict_types=1);

namespace Modules\Concours\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Modules\AcademicStructure\Models\Section;
use Modules\Concours\Models\Candidat;

/**
 * Sent to every admis after a results publication. Carries the orientation
 * (which section they're going to) and the rank within that section.
 */
final class ResultsPublishedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Candidat $candidat,
        public readonly Section $orientation,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject('Résultats du concours — Vous êtes admis(e)')
            ->greeting("Félicitations {$this->candidat->prenom} !")
            ->line('Les résultats du concours d\'entrée au Centre Universitaire de Koulamoutou viennent d\'être publiés.')
            ->line("Vous êtes **admis(e)** en **{$this->orientation->nom}**.")
            ->line(sprintf('• Moyenne : **%s** / 20', $this->candidat->moyenne ?? '—'))
            ->line(sprintf('• Rang dans votre filière : **%s**', $this->candidat->rang ?? '—'))
            ->action('Consulter mon dossier', route('concours.public.candidat.dashboard', $this->candidat->matricule_public))
            ->line('Un email séparé vous donnera les identifiants de connexion à votre espace étudiant.')
            ->salutation('Bienvenue au CUK !');
    }
}
