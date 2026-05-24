<?php

declare(strict_types=1);

namespace Modules\Concours\DTOs;

use App\Foundation\DTOs\Dto;

final readonly class ConfirmSelectionDto extends Dto
{
    public function __construct(
        public string $concoursSessionId,
        public string $publishedByUserId,
        /** @var array<int, array{candidat_id: string, orientation_section_id: string}> */
        public array $admis,
        public ?string $fichierPath = null,
        public ?string $fichierDisk = null,
        public ?string $communique = null,
    ) {}
}
