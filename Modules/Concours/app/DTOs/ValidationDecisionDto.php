<?php

declare(strict_types=1);

namespace Modules\Concours\DTOs;

use App\Foundation\DTOs\Dto;

final readonly class ValidationDecisionDto extends Dto
{
    public const DECISION_ACCEPT = 'accept';
    public const DECISION_REJECT = 'reject';

    public function __construct(
        public string $candidatId,
        public string $userId,
        public string $decision,                  // accept | reject
        /** @var array<int, string> */
        public array $motifs = [],                // required when decision = reject
    ) {}
}
