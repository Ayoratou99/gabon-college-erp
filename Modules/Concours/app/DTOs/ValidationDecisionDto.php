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
        /**
         * Set by the controller when the actor holds
         * `reject:candidats:validated` (super-admin / DG / DE). It unlocks
         * rejecting a dossier that is already paid/validated or admis — a
         * decision a chef-centre must never be able to take.
         */
        public bool $canRejectValidated = false,
    ) {}
}
