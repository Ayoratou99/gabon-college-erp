<?php

declare(strict_types=1);

namespace Modules\Concours\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Concours\DTOs\SchedulePlanningDto;

final class SchedulePlanningRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('manage:planning:*');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'epreuve_id'                  => ['required', 'uuid', 'exists:epreuves,id'],
            'concours_session_centre_id'  => ['required', 'uuid', 'exists:concours_session_centres,id'],
            'salle_id'                    => ['nullable', 'uuid', 'exists:salles,id'],
            'date_epreuve'                => ['required', 'date'],
            'heure_debut'                 => ['required', 'date_format:H:i'],
            'heure_fin'                   => ['required', 'date_format:H:i', 'after:heure_debut'],
            'consigne'                    => ['nullable', 'string'],
        ];
    }

    public function toDto(): SchedulePlanningDto
    {
        return new SchedulePlanningDto(
            epreuveId:                (string) $this->validated('epreuve_id'),
            concoursSessionCentreId:  (string) $this->validated('concours_session_centre_id'),
            salleId:                  $this->input('salle_id'),
            dateEpreuve:              (string) $this->validated('date_epreuve'),
            heureDebut:               (string) $this->validated('heure_debut'),
            heureFin:                 (string) $this->validated('heure_fin'),
            consigne:                 $this->input('consigne'),
        );
    }
}
