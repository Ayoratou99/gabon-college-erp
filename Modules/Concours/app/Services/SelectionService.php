<?php

declare(strict_types=1);

namespace Modules\Concours\Services;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Modules\AcademicStructure\Models\Section;
use Modules\Concours\DTOs\ConfirmSelectionDto;
use Modules\Concours\Exceptions\SelectionAlreadyPublishedException;
use Modules\Concours\Models\Candidat;
use Modules\Concours\Models\ConcoursSession;
use Modules\Concours\Models\ResultPublication;
use Modules\Concours\Notifications\ResultsPublishedNotification;
use Modules\UserManagement\Models\Role;
use Modules\UserManagement\Models\User;
use Modules\UserManagement\Notifications\WelcomeAdmisNotification;

/**
 * Two-phase selection:
 *
 *   1. suggest(session)         → returns a per-section ranked proposal
 *                                  (top N where N = section.places_par_session)
 *                                  WITHOUT mutating anything.
 *   2. confirm(dto)             → DG/DE submits the final list (which may
 *                                  diverge from the suggestion), atomically:
 *                                    a. marks each candidat statut='admis' + orientation
 *                                    b. creates the publication row + breakdown
 *                                    c. creates a User per admis (candidat role)
 *                                       and links candidat.user_id
 *
 * Idempotency: confirm() refuses to publish twice for the same session
 * unless the previous publication has been deactivated (`active=false`).
 */
final class SelectionService
{
    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly CandidatPromotionService $promotion,
    ) {}

    /**
     * @return Collection<string, array{section: Section, candidats: Collection<int, Candidat>}>
     *         keyed by section.id
     */
    public function suggest(string $sessionId): Collection
    {
        $sections = Section::query()
            ->where('ouvert_au_concours', true)
            ->where('active', true)
            ->get();

        $proposal = new Collection();

        foreach ($sections as $section) {
            $places = (int) $section->places_par_session;

            $candidats = Candidat::query()
                ->where('concours_session_id', $sessionId)
                ->where('section_premier_choix_id', $section->id)
                ->where('statut', Candidat::STATUS_VALID)
                ->whereNotNull('moyenne')
                ->orderByDesc('moyenne')
                ->orderBy('rang')
                ->orderBy('id')
                ->take($places)
                ->get(['id', 'nom', 'prenom', 'matricule_public', 'moyenne', 'rang']);

            $proposal->put($section->id, ['section' => $section, 'candidats' => $candidats]);
        }

        return $proposal;
    }

    public function confirm(ConfirmSelectionDto $dto): ResultPublication
    {
        if (ResultPublication::latestActiveFor($dto->concoursSessionId) !== null) {
            throw SelectionAlreadyPublishedException::for($dto->concoursSessionId);
        }

        $publication = $this->db->transaction(function () use ($dto): ResultPublication {
            $session = ConcoursSession::query()->findOrFail($dto->concoursSessionId);

            $admisIds = collect($dto->admis)->pluck('candidat_id')->all();
            $orientationByCandidat = collect($dto->admis)
                ->mapWithKeys(static fn (array $row): array => [
                    $row['candidat_id'] => $row['orientation_section_id'],
                ])->all();

            // 1. Mark admis
            foreach ($admisIds as $candidatId) {
                Candidat::query()->where('id', $candidatId)->update([
                    'statut'                  => Candidat::STATUS_ADMIS,
                    'section_orientation_id'  => $orientationByCandidat[$candidatId],
                    'admis_at'                => now(),
                ]);
            }

            // 2. Promote each admis candidat to an étudiant User account.
            //    The Candidat row is INTENTIONALLY untouched here — only step
            //    1 above writes to it (the legitimate `statut=admis` flip).
            //    The reverse link lives in `users.promoted_from_candidat_id`.
            //    The promotion is idempotent and skips placeholder contact
            //    info — see CandidatPromotionService for the full ruleset.
            foreach (Candidat::query()->whereIn('id', $admisIds)->get() as $candidat) {
                $result = $this->promotion->promote($candidat);
                if ($result['skipped_reason'] !== null) {
                    // Log so the DG can investigate without abandoning the
                    // whole publication.
                    Log::warning('Étudiant promotion skipped', [
                        'candidat_id'     => $candidat->getKey(),
                        'matricule'       => $candidat->matricule_public,
                        'skipped_reason'  => $result['skipped_reason'],
                    ]);
                }
            }

            // 3. Compute breakdown
            $breakdown = Candidat::query()
                ->where('concours_session_id', $session->id)
                ->where('statut', Candidat::STATUS_ADMIS)
                ->whereNotNull('section_orientation_id')
                ->selectRaw('section_orientation_id, COUNT(*) as n')
                ->groupBy('section_orientation_id')
                ->pluck('n', 'section_orientation_id')
                ->all();

            $totalCandidats = Candidat::query()
                ->where('concours_session_id', $session->id)
                ->where('statut', '!=', Candidat::STATUS_REJETE)
                ->count();

            // 4. Persist the publication row (active=true, partial unique index keeps it singleton)
            return ResultPublication::query()->create([
                'concours_session_id'    => $session->id,
                'published_by_user_id'   => $dto->publishedByUserId,
                'published_at'           => now(),
                'total_candidats'        => $totalCandidats,
                'total_admis'            => count($admisIds),
                'breakdown_par_section'  => $breakdown,
                'fichier_path'           => $dto->fichierPath,
                'fichier_disk'           => $dto->fichierDisk,
                'communique'             => $dto->communique,
                'active'                 => true,
            ]);
        });

        $this->notifyAdmis($dto->concoursSessionId);

        return $publication;
    }

    /**
     * Sends ResultsPublishedNotification to every admis with an email.
     * Sent *after* the transaction commits so a notification failure can't
     * roll the publication back.
     */
    private function notifyAdmis(string $sessionId): void
    {
        $admis = Candidat::query()
            ->where('concours_session_id', $sessionId)
            ->where('statut', Candidat::STATUS_ADMIS)
            ->whereNotNull('section_orientation_id')
            ->with('sectionOrientation:id,nom,code')
            ->get();

        foreach ($admis as $candidat) {
            if ($candidat->email === null || $candidat->email === '' || $candidat->sectionOrientation === null) {
                continue;
            }
            \App\Support\SafeNotifier::route('mail', $candidat->email, new ResultsPublishedNotification($candidat, $candidat->sectionOrientation));
        }
    }

    /**
     * Mints a User account for an admis candidat without setting a password —
     * the student activates the account through the first-login wizard
     * (verifying email+telephone, then choosing a password and enrolling 2FA).
     *
     * Reuses an existing User if the email already matches one in the DB
     * (handles the case of a returning student who already had an account).
     */
    private function createUserFor(Candidat $candidat): User
    {
        $candidatRoleId = Role::query()->where('code', 'candidat')->value('id');

        $existing = $candidat->email
            ? User::query()->whereRaw('LOWER(email) = ?', [mb_strtolower($candidat->email)])->first()
            : null;

        if ($existing !== null) {
            $existing->forceFill([
                'promoted_from_candidat_id' => $candidat->id,
                'telephone'                 => $existing->telephone ?: $candidat->telephone,
            ])->save();
            if ($candidatRoleId !== null) {
                $existing->roles()->syncWithoutDetaching([$candidatRoleId]);
            }
            // Existing accounts keep their password — they log in normally.
            return $existing;
        }

        $user = User::query()->create([
            'nom'                       => $candidat->nom,
            'prenom'                    => $candidat->prenom,
            'email'                     => $candidat->email,
            'telephone'                 => $candidat->telephone,
            'password'                  => null,           // set during first-login wizard
            'password_legacy'           => false,
            'must_set_password'         => true,
            'promoted_from_candidat_id' => $candidat->id,
        ]);

        if ($candidatRoleId !== null) {
            $user->roles()->syncWithoutDetaching([$candidatRoleId]);
        }

        // Notify the student that their account is awaiting activation. The
        // notification points to /connexion/premiere-fois; the actual
        // credentials (email + tel) the student already knows from
        // registration so we don't need to re-send them.
        \App\Support\SafeNotifier::send($user, new WelcomeAdmisNotification($user));

        return $user;
    }
}
