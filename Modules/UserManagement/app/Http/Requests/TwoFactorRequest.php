<?php

declare(strict_types=1);

namespace Modules\UserManagement\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class TwoFactorRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Only callable if a pre-auth user is in session.
        $key = config('usermanagement.two_factor.session_keys.pre_auth_user_id');
        return $this->session()->has($key);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'otp' => ['required', 'string', 'size:6', 'regex:/^\d{6}$/'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'otp.required' => 'Code à 6 chiffres requis.',
            'otp.size'     => 'Le code doit faire exactement 6 chiffres.',
            'otp.regex'    => 'Le code ne doit contenir que des chiffres.',
        ];
    }

    public function otp(): string
    {
        return (string) $this->validated('otp');
    }
}
