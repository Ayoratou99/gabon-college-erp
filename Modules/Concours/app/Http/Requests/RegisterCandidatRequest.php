<?php

declare(strict_types=1);

namespace Modules\Concours\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Concours\DTOs\RegisterCandidatDto;
use Modules\Concours\Models\ConcoursSession;
use Modules\Concours\Support\PhoneNumber;

/**
 * Validates the *whole* registration payload (identité + bac + choix + photo
 * + documents) so the service layer can trust the DTO it receives.
 *
 * Email/telephone uniqueness inside the active session is enforced both by
 * Postgres partial unique indexes (defence in depth) and by the validator
 * rules below (friendly UX message).
 */
final class RegisterCandidatRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Canonicalise the phone number BEFORE validation so separators the user
     * typed ("066-22-88-77") are stripped to digits, the regex below sees the
     * clean value, and the per-session uniqueness check compares like-for-like.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('telephone')) {
            $this->merge(['telephone' => PhoneNumber::normalize($this->input('telephone'))]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $sessionId = (string) ConcoursSession::publicCurrent()?->getKey();

        return [
            'centre_id'                  => ['required', 'uuid', 'exists:centres,id'],

            'nom'                        => ['required', 'string', 'max:100'],
            'prenom'                     => ['required', 'string', 'max:100'],
            'date_naissance'             => ['required', 'date', 'before:today'],
            'lieu_naissance'             => ['required', 'string', 'max:100'],
            'sexe'                       => ['required', 'in:M,F'],
            'nationalite_id'             => ['required', 'uuid', 'exists:nationalites,id'],

            'email'                      => [
                'required', 'email:rfc', 'max:191',
                "unique:candidats,email,NULL,id,concours_session_id,{$sessionId},deleted_at,NULL",
            ],
            'telephone'                  => [
                'required', 'string', 'regex:' . PhoneNumber::REGEX,
                "unique:candidats,telephone,NULL,id,concours_session_id,{$sessionId},deleted_at,NULL",
            ],

            'deja_bac'                   => ['required', 'boolean'],
            'annee_bac'                  => ['nullable', 'required_if:deja_bac,true', 'integer', 'min:1980', 'max:' . (int) date('Y')],
            'serie_bac_id'               => ['required', 'uuid', 'exists:series_bac,id'],
            'bac_libelle_libre'          => ['nullable', 'string', 'max:191'],
            'etablissement_frequente'    => ['required', 'string', 'max:191'],

            'section_premier_choix_id'   => ['required', 'uuid', 'exists:sections,id'],
            'section_second_choix_id'    => ['nullable', 'uuid', 'exists:sections,id', 'different:section_premier_choix_id'],

            'photo'                      => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],

            // Documents arrive as `documents[acte]=<file>` etc. — keyed by DocumentRequis.code.
            'documents'                  => ['required', 'array', 'min:1'],
            'documents.*'                => ['file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'annee_bac.required_if' => 'Précisez l\'année d\'obtention de votre BAC.',
            'section_second_choix_id.different' => 'Le second choix doit être différent du premier.',
            'email.unique'     => 'Un dossier existe déjà avec cet email pour ce concours.',
            'telephone.unique' => 'Un dossier existe déjà avec ce téléphone pour ce concours.',
            'telephone.regex'  => 'Le téléphone ne doit contenir que des chiffres (ex. 066228877), éventuellement précédés de « + ».',
        ];
    }

    public function toDto(): RegisterCandidatDto
    {
        $sessionId = (string) ConcoursSession::publicCurrent()?->getKey();

        /** @var array<string, \Illuminate\Http\UploadedFile> $documents */
        $documents = $this->file('documents') ?? [];

        return new RegisterCandidatDto(
            concoursSessionId:       $sessionId,
            centreId:                (string) $this->validated('centre_id'),
            nom:                     (string) $this->validated('nom'),
            prenom:                  (string) $this->validated('prenom'),
            dateNaissance:           (string) $this->validated('date_naissance'),
            lieuNaissance:           (string) $this->validated('lieu_naissance'),
            sexe:                    (string) $this->validated('sexe'),
            nationaliteId:           (string) $this->validated('nationalite_id'),
            email:                   (string) $this->validated('email'),
            telephone:               (string) $this->validated('telephone'),
            dejaBac:                 (bool)   $this->boolean('deja_bac'),
            anneeBac:                $this->input('deja_bac') ? (int) $this->validated('annee_bac') : null,
            serieBacId:              (string) $this->validated('serie_bac_id'),
            bacLibelleLibre:         $this->input('bac_libelle_libre'),
            etablissementFrequente:  (string) $this->validated('etablissement_frequente'),
            sectionPremierChoixId:   (string) $this->validated('section_premier_choix_id'),
            sectionSecondChoixId:    $this->input('section_second_choix_id'),
            photo:                   $this->file('photo'),
            documents:               $documents,
        );
    }
}
