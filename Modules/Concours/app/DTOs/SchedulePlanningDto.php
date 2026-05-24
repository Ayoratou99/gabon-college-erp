<?php

declare(strict_types=1);

namespace Modules\Concours\DTOs;

use App\Foundation\DTOs\Dto;

final readonly class SchedulePlanningDto extends Dto
{
    public function __construct(
        public string $epreuveId,
        public string $concoursSessionCentreId,
        public ?string $salleId,
        public string $dateEpreuve,    // Y-m-d
        public string $heureDebut,     // HH:MM
        public string $heureFin,       // HH:MM
        public ?string $consigne = null,
    ) {}
}
