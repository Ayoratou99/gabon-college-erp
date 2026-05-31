<?php

declare(strict_types=1);

namespace Modules\Concours\Services;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\Notification;
use Modules\Concours\Models\Candidat;
use Modules\Concours\Notifications\AdmisPromotedNotification;
use Modules\UserManagement\Models\Role;
use Modules\UserManagement\Models\User;

/**
 * Promotes an admis candidat into a `etudiant` User account.
 *
 *   - The Candidat row is INTENTIONALLY untouched. The application
 *     dossier is the immutable record of the admission and we don't
 *     mutate it (no statut change, no candidat.user_id write, no flag).
 *
 *   - A new `users` row is created with `promoted_from_candidat_id` set
 *     so the User can find their original dossier later. That is the
 *     ONLY link.
 *
 *   - The new User starts unactivated: password is null, must_set_password
 *     is true. They'll go through the existing FirstLoginController flow
 *     (email + tel → password → 2FA → logged in).
 *
 *   - The `etudiant` role is attached. They have no admin permissions.
 *
 *   - Idempotent: re-running on a candidat that's already been promoted
 *     returns the existing User row instead of throwing. This keeps the
 *     "re-publish results" path safe.
 *
 *   - Refuses to promote candidats with placeholder contact info (legacy
 *     `@cuk.local` email or `LEGACY-…` phone) — they can't receive the
 *     activation notification or identify themselves in first-login.
 *
 * The notification is fired only on the FIRST successful promotion (when
 * we actually create the User), not on idempotent re-runs.
 */
final class CandidatPromotionService
{
    public function __construct(
        private readonly ConnectionInterface $db,
    ) {}

    /**
     * @return array{user: User, created: bool, skipped_reason: ?string}
     *         - user           the resulting User row (or null when skipped)
     *         - created        true if we just inserted it, false on re-run
     *         - skipped_reason set when we refused to promote (legacy data,
     *                          not admis, …); user is null in that case
     */
    public function promote(Candidat $candidat): array
    {
        if ($candidat->statut !== Candidat::STATUS_ADMIS) {
            return ['user' => null, 'created' => false, 'skipped_reason' => 'not_admis'];
        }

        // Existing promotion? Return it unchanged — we never re-issue
        // credentials, that's the admin's job via the existing reset
        // password / reset 2FA buttons.
        $existing = User::query()
            ->where('promoted_from_candidat_id', $candidat->getKey())
            ->first();
        if ($existing !== null) {
            return ['user' => $existing, 'created' => false, 'skipped_reason' => null];
        }

        $email = (string) $candidat->email;
        $tel   = (string) $candidat->telephone;
        if ($email === '' || str_ends_with($email, '@cuk.local') || str_starts_with($tel, 'LEGACY-')) {
            return [
                'user' => null, 'created' => false,
                'skipped_reason' => 'placeholder_contact',
            ];
        }

        // Also block when the email is ALREADY used by a different User row
        // (chef-centre with same address registered both ways, admin who
        // happened to use the same address, etc.). The admin will see this
        // in the publication summary and decide how to resolve.
        $emailClash = User::query()
            ->whereRaw('LOWER(email) = ?', [mb_strtolower($email)])
            ->exists();
        if ($emailClash) {
            return [
                'user' => null, 'created' => false,
                'skipped_reason' => 'email_already_used',
            ];
        }

        $user = $this->db->transaction(function () use ($candidat, $email, $tel): User {
            /** @var User $user */
            $user = User::query()->create([
                'nom'                       => $candidat->nom,
                'prenom'                    => $candidat->prenom,
                'email'                     => mb_strtolower($email),
                'telephone'                 => $tel,
                'password'                  => null,
                'password_legacy'           => false,
                'must_set_password'         => true,
                'promoted_from_candidat_id' => $candidat->getKey(),
            ]);

            $etudiantRole = Role::query()->where('code', 'etudiant')->first();
            if ($etudiantRole !== null) {
                $user->roles()->attach($etudiantRole);
            }

            return $user;
        });

        if ($email !== '') {
            Notification::route('mail', $email)
                ->notify(new AdmisPromotedNotification(
                    candidat: $candidat,
                    user: $user,
                ));
        }

        return ['user' => $user, 'created' => true, 'skipped_reason' => null];
    }
}
