<?php

declare(strict_types=1);

namespace Modules\UserManagement\Events;

use Illuminate\Foundation\Events\Dispatchable;

final class LoginFailed
{
    use Dispatchable;

    public function __construct(
        public readonly string $identifier,
        public readonly string $ipAddress,
        public readonly ?string $userAgent,
        public readonly string $reason,
        public readonly ?string $userId,
    ) {}
}
