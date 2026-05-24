<?php

declare(strict_types=1);

namespace Modules\Concours\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Concours\DTOs\ConfirmSelectionDto;

final class ConfirmSelectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('publish:results:*');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'concours_session_id'                => ['required', 'uuid', 'exists:concours_sessions,id'],
            'admis'                              => ['required', 'array', 'min:1'],
            'admis.*.candidat_id'                => ['required', 'uuid', 'exists:candidats,id'],
            'admis.*.orientation_section_id'     => ['required', 'uuid', 'exists:sections,id'],
            'communique'                         => ['nullable', 'string', 'max:5000'],
            'fichier_path'                       => ['nullable', 'string', 'max:500'],
            'fichier_disk'                       => ['nullable', 'string', 'max:50'],
        ];
    }

    public function toDto(string $userId): ConfirmSelectionDto
    {
        return new ConfirmSelectionDto(
            concoursSessionId: (string) $this->validated('concours_session_id'),
            publishedByUserId: $userId,
            admis:             (array) $this->validated('admis'),
            fichierPath:       $this->input('fichier_path'),
            fichierDisk:       $this->input('fichier_disk'),
            communique:        $this->input('communique'),
        );
    }
}
