<?php

declare(strict_types=1);

namespace Modules\Concours\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Concours\DTOs\UpdateCandidatDto;
use Modules\Concours\Models\Candidat;
use Modules\Concours\Models\CandidatModification;

/**
 * Validates a public modification submission for a rejected dossier.
 *
 * Authorization == "session token still valid and matches the URL token".
 * The candidat being edited is resolved from the session, NOT from the URL,
 * so a forged URL alone can never address a different dossier.
 *
 * Email + telephone uniqueness rules ignore the current candidat's own row
 * so re-saving without changing them is a no-op (not a 422).
 */
final class ModifyCandidatRequest extends FormRequest
{
    private ?Candidat $candidat = null;

    public function authorize(): bool
    {
        $keys      = (array) config('concours.public_lookup.session_keys');
        $token     = $this->session()->get($keys['modification_token']);
        $expiresAt = $this->session()->get($keys['modification_expires']);

        return is_string($token)
            && $token === $this->route('token')
            && is_int($expiresAt)
            && $expiresAt >= time();
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $candidat   = $this->candidat();
        $candidatId = $candidat->getKey();
        $sessionId  = $candidat->concours_session_id;

        return [
            'nom'                       => ['required', 'string', 'max:100'],
            'prenom'                    => ['required', 'string', 'max:100'],
            'date_naissance'            => ['required', 'date', 'before:today'],
            'lieu_naissance'            => ['required', 'string', 'max:100'],
            'sexe'                      => ['required', 'in:M,F'],
            'nationalite_id'            => ['required', 'uuid', 'exists:nationalites,id'],

            'email'                     => [
                'required', 'email:rfc', 'max:191',
                "unique:candidats,email,{$candidatId},id,concours_session_id,{$sessionId},deleted_at,NULL",
            ],
            'telephone'                 => [
                'required', 'string', 'regex:/^[+0-9 .-]{6,30}$/',
                "unique:candidats,telephone,{$candidatId},id,concours_session_id,{$sessionId},deleted_at,NULL",
            ],

            'deja_bac'                  => ['required', 'boolean'],
            'annee_bac'                 => ['nullable', 'required_if:deja_bac,true', 'integer', 'min:1980', 'max:' . date('Y')],
            'serie_bac_id'              => ['required', 'uuid', 'exists:series_bac,id'],
            'bac_libelle_libre'         => ['nullable', 'string', 'max:191'],
            'etablissement_frequente'   => ['required', 'string', 'max:191'],

            'section_premier_choix_id'  => ['required', 'uuid', 'exists:sections,id'],
            'section_second_choix_id'   => ['nullable', 'uuid', 'exists:sections,id', 'different:section_premier_choix_id'],
            'centre_id'                 => ['required', 'uuid', 'exists:centres,id'],

            'photo'                     => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'documents'                 => ['nullable', 'array'],
            'documents.*'               => ['file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],

            'reason'                    => ['nullable', 'string', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'email.unique'     => 'Un autre dossier utilise déjà cet email pour ce concours.',
            'telephone.unique' => 'Un autre dossier utilise déjà ce téléphone pour ce concours.',
            'section_second_choix_id.different' => 'Le second choix doit être différent du premier.',
            'annee_bac.required_if' => 'Précisez l\'année d\'obtention de votre BAC.',
        ];
    }

    public function candidat(): Candidat
    {
        if ($this->candidat !== null) {
            return $this->candidat;
        }
        $candidatId = $this->session()->get(config('concours.public_lookup.session_keys.modification_candidat'));
        return $this->candidat = Candidat::query()->findOrFail($candidatId);
    }

    public function toDto(): UpdateCandidatDto
    {
        $validated = $this->validated();

        /** @var array<string, \Illuminate\Http\UploadedFile>|null $documents */
        $documents = $this->file('documents');

        return new UpdateCandidatDto(
            candidatId:              $this->candidat()->getKey(),
            channel:                 CandidatModification::CHANNEL_PUBLIC,
            userId:                  null,
            ipAddress:               $this->ip(),
            reason:                  $validated['reason'] ?? 'Modification publique après rejet',
            nom:                     $validated['nom'],
            prenom:                  $validated['prenom'],
            dateNaissance:           $validated['date_naissance'],
            lieuNaissance:           $validated['lieu_naissance'],
            sexe:                    $validated['sexe'],
            nationaliteId:           $validated['nationalite_id'],
            email:                   $validated['email'],
            telephone:               $validated['telephone'],
            dejaBac:                 (bool) $validated['deja_bac'],
            anneeBac:                isset($validated['annee_bac']) ? (int) $validated['annee_bac'] : null,
            serieBacId:              $validated['serie_bac_id'],
            bacLibelleLibre:         $validated['bac_libelle_libre'] ?? null,
            etablissementFrequente:  $validated['etablissement_frequente'],
            sectionPremierChoixId:   $validated['section_premier_choix_id'],
            sectionSecondChoixId:    $validated['section_second_choix_id'] ?? null,
            centreId:                $validated['centre_id'],
            photo:                   $this->file('photo'),
            documents:               $documents ?: null,
        );
    }
}
