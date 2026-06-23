<?php

declare(strict_types=1);

namespace Modules\Concours\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Concours\Models\Candidat;
use Modules\Concours\Models\CandidatModification;
use Modules\Concours\DTOs\UpdateCandidatDto;
use Modules\Concours\Support\PhoneNumber;

/**
 * Admin-side update of a candidat (identity, contact, academic, choix).
 * Companion to the public ModifyCandidatRequest — same target rows, very
 * different authorization model.
 *
 * Authorization:
 *   - Caller must hold `edit:candidats:*` (DG / DE / super-admin) OR
 *     `edit:candidats:own_center` and the candidat must be in one of
 *     their accessible centres (chef-centre).
 *   - A chef-centre is also **forbidden** to change `centre_id` — they
 *     can edit candidats inside their centre but cannot move someone
 *     out of it. That guard is enforced in `prepareForValidation` (we
 *     strip the field before the rules run).
 *
 * Partial updates: every field is `sometimes` so the admin UI only sends
 * what they actually edited. The audit row is per-field, written by
 * CandidatModificationService — fields the request didn't carry stay
 * untouched and write no audit row.
 *
 * Statut, matricule_public, photo, documents are NOT accepted through this
 * endpoint:
 *   - statut goes through CandidatValidationService::decide
 *   - matricule_public is immutable
 *   - photo / documents are managed by the documents pipeline (separate task)
 */
final class AdminUpdateCandidatRequest extends FormRequest
{
    public function authorize(): bool
    {
        $candidat = $this->routeCandidat();
        if (! $candidat instanceof Candidat) {
            return false;
        }
        // Two passing paths: the global edit:* permission, or the
        // own_center scoped one (the foundation Gate resolves the scope
        // via Candidat::scopeColumnFor + the UserScopeResolver).
        $user = $this->user();
        if ($user === null) {
            return false;
        }
        return $user->can('edit:candidats:*', $candidat)
            || $user->can('edit:candidats:own_center', $candidat);
    }

    protected function prepareForValidation(): void
    {
        // Chef-centre cannot reassign a candidat to a different centre,
        // even though they have edit:candidats:own_center on this row.
        // Silently drop the field rather than 422 — the UI shouldn't have
        // exposed the control in the first place, so this is defence in
        // depth against a hand-crafted request.
        $user = $this->user();
        if ($user !== null
            && ! $user->can('edit:candidats:*')
            && $this->has('centre_id')
        ) {
            $this->request->remove('centre_id');
        }

        // Canonicalise the phone (strip separators) so the regex + per-session
        // uniqueness check below see digits-only, like the public flows.
        if ($this->has('telephone')) {
            $this->merge(['telephone' => PhoneNumber::normalize($this->input('telephone'))]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $candidat   = $this->routeCandidat();
        $candidatId = $candidat?->getKey();
        $sessionId  = $candidat?->concours_session_id;

        // All rules are `sometimes` so partial updates work — the admin UI
        // only POSTs fields it actually changed.
        return [
            'nom'                      => ['sometimes', 'required', 'string', 'max:100'],
            'prenom'                   => ['sometimes', 'required', 'string', 'max:100'],
            'date_naissance'           => ['sometimes', 'required', 'date', 'before:today'],
            'lieu_naissance'           => ['sometimes', 'required', 'string', 'max:100'],
            'sexe'                     => ['sometimes', 'required', 'in:M,F'],
            'nationalite_id'           => ['sometimes', 'required', 'uuid', 'exists:nationalites,id'],

            'email'                    => [
                'sometimes', 'required', 'email:rfc', 'max:191',
                "unique:candidats,email,{$candidatId},id,concours_session_id,{$sessionId},deleted_at,NULL",
            ],
            'telephone'                => [
                'sometimes', 'required', 'string', 'regex:' . PhoneNumber::REGEX,
                "unique:candidats,telephone,{$candidatId},id,concours_session_id,{$sessionId},deleted_at,NULL",
            ],

            'deja_bac'                 => ['sometimes', 'required', 'boolean'],
            'annee_bac'                => ['sometimes', 'nullable', 'required_if:deja_bac,true', 'integer', 'min:1980', 'max:' . date('Y')],
            'serie_bac_id'             => ['sometimes', 'required', 'uuid', 'exists:series_bac,id'],
            'bac_libelle_libre'        => ['sometimes', 'nullable', 'string', 'max:191'],
            'etablissement_frequente'  => ['sometimes', 'required', 'string', 'max:191'],

            'section_premier_choix_id' => ['sometimes', 'required', 'uuid', 'exists:sections,id'],
            'section_second_choix_id'  => ['sometimes', 'nullable', 'uuid', 'exists:sections,id', 'different:section_premier_choix_id'],
            'centre_id'                => ['sometimes', 'required', 'uuid', 'exists:centres,id'],

            'reason'                   => ['nullable', 'string', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'email.unique'                      => 'Un autre dossier utilise déjà cet email pour ce concours.',
            'telephone.unique'                  => 'Un autre dossier utilise déjà ce téléphone pour ce concours.',
            'telephone.regex'                   => 'Le téléphone ne doit contenir que des chiffres (ex. 066228877), éventuellement précédés de « + ».',
            'section_second_choix_id.different' => 'Le second choix doit être différent du premier.',
            'annee_bac.required_if'             => 'Précisez l\'année d\'obtention du BAC.',
        ];
    }

    public function toDto(): UpdateCandidatDto
    {
        $validated = $this->validated();
        $user      = $this->user();

        return new UpdateCandidatDto(
            candidatId:             $this->routeCandidat()->getKey(),
            channel:                CandidatModification::CHANNEL_ADMIN,
            userId:                 $user !== null ? (string) $user->getAuthIdentifier() : null,
            ipAddress:              $this->ip(),
            reason:                 $validated['reason'] ?? null,

            nom:                    $validated['nom']                       ?? null,
            prenom:                 $validated['prenom']                    ?? null,
            dateNaissance:          $validated['date_naissance']            ?? null,
            lieuNaissance:          $validated['lieu_naissance']            ?? null,
            sexe:                   $validated['sexe']                      ?? null,
            nationaliteId:          $validated['nationalite_id']            ?? null,
            email:                  $validated['email']                     ?? null,
            telephone:              $validated['telephone']                 ?? null,
            dejaBac:                isset($validated['deja_bac']) ? (bool) $validated['deja_bac'] : null,
            anneeBac:               isset($validated['annee_bac']) ? (int) $validated['annee_bac'] : null,
            serieBacId:             $validated['serie_bac_id']              ?? null,
            bacLibelleLibre:        $validated['bac_libelle_libre']         ?? null,
            etablissementFrequente: $validated['etablissement_frequente']   ?? null,
            sectionPremierChoixId:  $validated['section_premier_choix_id']  ?? null,
            sectionSecondChoixId:   $validated['section_second_choix_id']   ?? null,
            centreId:               $validated['centre_id']                 ?? null,
            // photo / documents NEVER through admin edit — those have a
            // dedicated upload pipeline.
            photo:                  null,
            documents:              null,
        );
    }

    private function routeCandidat(): ?Candidat
    {
        $bound = $this->route('candidat');
        return $bound instanceof Candidat ? $bound : null;
    }
}
