<?php

declare(strict_types=1);

namespace Modules\Concours\DTOs;

use App\Foundation\DTOs\Dto;
use Illuminate\Http\UploadedFile;

/**
 * Used by both the public modification flow (post-rejection) and the
 * back-office edit by chef-centre / DE. The `channel` field is set by
 * the calling service so the audit log captures the source correctly.
 *
 * Any field set to `null` means "no change".
 */
final readonly class UpdateCandidatDto extends Dto
{
    public function __construct(
        public string $candidatId,
        public string $channel,          // public | admin
        public ?string $userId,           // null for public channel
        public ?string $ipAddress,
        public ?string $reason,           // explanation captured for the audit log

        public ?string $nom = null,
        public ?string $prenom = null,
        public ?string $dateNaissance = null,
        public ?string $lieuNaissance = null,
        public ?string $sexe = null,
        public ?string $nationaliteId = null,
        public ?string $email = null,
        public ?string $telephone = null,
        public ?bool $dejaBac = null,
        public ?int $anneeBac = null,
        public ?string $serieBacId = null,
        public ?string $bacLibelleLibre = null,
        public ?string $etablissementFrequente = null,
        public ?string $sectionPremierChoixId = null,
        public ?string $sectionSecondChoixId = null,
        public ?string $centreId = null,
        public ?UploadedFile $photo = null,
        /** @var array<string, UploadedFile>|null */
        public ?array $documents = null,
    ) {}
}
