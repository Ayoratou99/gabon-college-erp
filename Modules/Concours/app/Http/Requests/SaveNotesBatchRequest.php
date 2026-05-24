<?php

declare(strict_types=1);

namespace Modules\Concours\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Concours\DTOs\SaveNotesBatchDto;

final class SaveNotesBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('enter:notes:*')
            || (bool) $this->user()?->can('enter:notes:own_center');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'epreuve_id'             => ['required', 'uuid', 'exists:epreuves,id'],
            'entries'                => ['required', 'array', 'min:1'],
            'entries.*.candidat_id'  => ['required', 'uuid', 'exists:candidats,id'],
            'entries.*.valeur'       => ['nullable', 'numeric', 'min:0', 'max:100'],
            'entries.*.absent'       => ['sometimes', 'boolean'],
            'entries.*.commentaire'  => ['nullable', 'string', 'max:500'],
            'lock'                   => ['sometimes', 'boolean'],
        ];
    }

    public function toDto(string $userId): SaveNotesBatchDto
    {
        return new SaveNotesBatchDto(
            epreuveId: (string) $this->validated('epreuve_id'),
            userId:    $userId,
            entries:   (array) $this->validated('entries'),
            lock:      (bool) $this->boolean('lock'),
        );
    }
}
