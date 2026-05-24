<?php

declare(strict_types=1);

namespace Modules\Parametrage\DTOs;

use App\Foundation\DTOs\Dto;

final readonly class SetSettingDto extends Dto
{
    public function __construct(
        public string $key,
        public mixed $value,
        public ?string $ipAddress = null,
    ) {}
}
