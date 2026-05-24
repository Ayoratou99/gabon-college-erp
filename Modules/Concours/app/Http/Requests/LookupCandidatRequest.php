<?php

declare(strict_types=1);

namespace Modules\Concours\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class LookupCandidatRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'email'     => ['required', 'email:rfc', 'max:191'],
            'telephone' => ['required', 'string', 'regex:/^[+0-9 .-]{6,30}$/'],
        ];
    }
}
