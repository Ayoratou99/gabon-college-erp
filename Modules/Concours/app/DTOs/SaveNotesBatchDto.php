<?php

declare(strict_types=1);

namespace Modules\Concours\DTOs;

use App\Foundation\DTOs\Dto;

/**
 * Bulk note-entry payload: one epreuve, many candidats.
 *
 * Each entry: {candidat_id, valeur|null, absent: bool, commentaire?}
 */
final readonly class SaveNotesBatchDto extends Dto
{
    public function __construct(
        public string $epreuveId,
        public string $userId,
        /** @var array<int, array{candidat_id: string, valeur: float|null, absent: bool, commentaire?: ?string}> */
        public array $entries,
        public bool $lock = false,
    ) {}
}
