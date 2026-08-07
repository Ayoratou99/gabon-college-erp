<?php

declare(strict_types=1);

namespace Modules\Concours\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Concours\DTOs\ValidationDecisionDto;
use Modules\Concours\Models\Candidat;

final class ValidationDecisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Candidat|null $candidat */
        $candidat = $this->route('candidat');
        if (! $candidat instanceof Candidat) {
            return false;
        }
        // RBAC check resolves via Gate::before → PermissionChecker
        return (bool) $this->user()?->can('validate:candidats:*', $candidat);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'decision' => ['required', 'in:accept,reject'],
            'motifs'   => ['required_if:decision,reject', 'array', 'min:1'],
            'motifs.*' => ['string', 'max:500'],
        ];
    }

    public function toDto(string $candidatId, string $userId): ValidationDecisionDto
    {
        return new ValidationDecisionDto(
            candidatId: $candidatId,
            userId:     $userId,
            decision:   (string) $this->validated('decision'),
            motifs:     (array) $this->input('motifs', []),
            // Only super-admin / DG / DE may annul a dossier that has already
            // been validated (payment received) or admitted. A chef-centre with
            // validate:candidats:own_center keeps the ordinary
            // "en cours → rejeté" power and nothing more.
            canRejectValidated: (bool) $this->user()?->can('reject:candidats:validated'),
        );
    }
}
