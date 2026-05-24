<?php

declare(strict_types=1);

namespace Modules\UserManagement\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\UserManagement\Models\User;

final class LoginSucceeded
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly string $ipAddress,
        public readonly ?string $userAgent,
        public readonly bool $rehashedFromLegacy,
    ) {}
}
