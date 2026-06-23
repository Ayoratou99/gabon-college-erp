<?php

declare(strict_types=1);

namespace Modules\Concours\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Concours\Support\PhoneNumber;

final class LookupCandidatRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normalise the phone the same way the registration flow does, so a lookup
     * typed "066-22-88-77" matches the stored (digits-only) "066228877".
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
        return [
            'email'     => ['required', 'email:rfc', 'max:191'],
            'telephone' => ['required', 'string', 'regex:' . PhoneNumber::REGEX],
        ];
    }
}
