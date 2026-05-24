<?php

declare(strict_types=1);

namespace Modules\UserManagement\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\UserManagement\DTOs\LoginCredentialsDto;

final class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // open to guests
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'identifier' => ['required', 'string', 'max:191'],
            'password'   => ['required', 'string', 'min:6', 'max:128'],
            'remember'   => ['sometimes', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'identifier.required' => 'Veuillez saisir votre identifiant (email ou téléphone).',
            'password.required'   => 'Le mot de passe est obligatoire.',
            'password.min'        => 'Le mot de passe doit comporter au moins :min caractères.',
        ];
    }

    public function toDto(): LoginCredentialsDto
    {
        return new LoginCredentialsDto(
            identifier:      trim((string) $this->validated('identifier')),
            password:        (string) $this->validated('password'),
            ipAddress:       (string) $this->ip(),
            userAgent:       $this->userAgent(),
            recaptchaToken:  $this->input('g-recaptcha-response'),
            remember:        (bool) $this->boolean('remember'),
        );
    }
}
