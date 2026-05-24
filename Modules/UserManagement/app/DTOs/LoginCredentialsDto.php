<?php

declare(strict_types=1);

namespace Modules\UserManagement\DTOs;

use App\Foundation\DTOs\Dto;

final readonly class LoginCredentialsDto extends Dto
{
    public function __construct(
        /** Either email or phone — see ::looksLikeEmail() */
        public string $identifier,
        public string $password,
        public string $ipAddress,
        public ?string $userAgent,
        public ?string $recaptchaToken,
        public bool $remember = false,
    ) {}

    public function looksLikeEmail(): bool
    {
        return str_contains($this->identifier, '@');
    }
}
