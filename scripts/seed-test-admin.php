<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// One-shot helper to create a known super-admin so we can smoke-test the
// authenticated admin pages. Idempotent on telephone.
$user = \Modules\UserManagement\Models\User::query()->updateOrCreate(
    ['telephone' => '060000000'],
    [
        'nom'             => 'TEST',
        'prenom'          => 'Admin',
        'email'           => 'admin@test.local',
        'password'        => 'admin1234',           // hashed via 'hashed' cast
        'password_legacy' => false,
        // 2FA disabled for the smoke test:
        'google2fa_secret'       => null,
        'google2fa_confirmed_at' => null,
    ],
);

$role = \Modules\UserManagement\Models\Role::query()->where('code', 'super-admin')->first();
if ($role !== null) {
    $user->roles()->syncWithoutDetaching([$role->id]);
}

// Mark the user as already 2FA-verified by giving them a fake secret + confirmation
// so the smoke test can bypass the OTP step.
$user->forceFill([
    'google2fa_secret'       => 'JBSWY3DPEHPK3PXP',
    'google2fa_confirmed_at' => now(),
])->save();

echo "test admin ready: telephone=060000000  password=admin1234  user_id={$user->id}\n";
