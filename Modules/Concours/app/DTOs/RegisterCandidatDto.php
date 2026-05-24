<?php

declare(strict_types=1);

namespace Modules\Concours\DTOs;

use App\Foundation\DTOs\Dto;
use Illuminate\Http\UploadedFile;

/**
 * The complete payload of a public registration form submission.
 *
 * `documents` is keyed by DocumentRequis.code (e.g. "acte", "rnbac") so the
 * upload service can look up each declared document's allowed formats /
 * max size without an extra DB query per file.
 *
 * @phpstan-type DocumentMap array<string, UploadedFile>
 */
final readonly class RegisterCandidatDto extends Dto
{
    public function __construct(
        // Concours context
        public string $concoursSessionId,
        public string $centreId,

        // Identité
        public string $nom,
        public string $prenom,
        public string $dateNaissance,    // Y-m-d
        public string $lieuNaissance,
        public string $sexe,             // M | F
        public string $nationaliteId,
        public string $email,
        public string $telephone,

        // Bac
        public bool $dejaBac,
        public ?int $anneeBac,
        public string $serieBacId,
        public ?string $bacLibelleLibre,
        public string $etablissementFrequente,

        // Choix
        public string $sectionPremierChoixId,
        public ?string $sectionSecondChoixId,

        // Pieces
        public UploadedFile $photo,
        /** @var DocumentMap */
        public array $documents,
    ) {}
}
