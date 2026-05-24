<?php

declare(strict_types=1);

namespace Modules\Concours\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Concours\Models\Epreuve;

final class CreateEpreuveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create:epreuves:*');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return Epreuve::validationRules();
    }
}
