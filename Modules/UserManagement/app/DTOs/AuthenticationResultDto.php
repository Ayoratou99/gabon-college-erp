<?php

declare(strict_types=1);

namespace Modules\UserManagement\DTOs;

use App\Foundation\DTOs\Dto;
use Modules\UserManagement\Models\User;

/**
 * Outcome of an AuthenticationService::authenticate() call.
 *
 * The Controller looks at $requiresTwoFactor to decide where to send the
 * browser next: enrolment screen, OTP screen, or straight to the dashboard.
 */
final readonly class AuthenticationResultDto extends Dto
{
    public function __construct(
        public User $user,
        public bool $requiresTwoFactor,
        public bool $requiresEnrollment,
        public bool $rehashedFromLegacy,
    ) {}
}
