<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\UserManagement\Models\Role;
use Modules\UserManagement\Models\User;
use PragmaRX\Google2FA\Google2FA;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(Modules\UserManagement\Database\Seeders\RoleSeeder::class);
});

function loginAndPreAuth(User $user): void
{
    test()->post('/login', [
        'identifier' => $user->telephone,
        'password'   => 'pa55w0rd!',
    ]);
}

it('redirects users without an enrolled 2FA to the enrollment page', function (): void {
    $user = User::factory()->create([
        'telephone' => '066111222',
        'password'  => 'pa55w0rd!',
        'google2fa_secret'       => null,
        'google2fa_confirmed_at' => null,
    ]);
    $user->roles()->sync([Role::query()->where('code', 'de')->value('id')]);

    loginAndPreAuth($user);

    $this->get('/two-factor/enroll')
        ->assertOk()
        ->assertSee('Activez la double authentification');
});

it('rejects an invalid OTP on challenge', function (): void {
    $google = new Google2FA();
    $secret = $google->generateSecretKey();

    $user = User::factory()->create([
        'telephone' => '066333444',
        'password'  => 'pa55w0rd!',
        'google2fa_secret'       => $secret,
        'google2fa_confirmed_at' => now(),
    ]);
    $user->roles()->sync([Role::query()->where('code', 'de')->value('id')]);

    loginAndPreAuth($user);

    $this->post('/two-factor/challenge', ['otp' => '000000'])
        ->assertSessionHasErrors('otp');
});

it('accepts a valid OTP and logs the user in', function (): void {
    $google = new Google2FA();
    $secret = $google->generateSecretKey();

    $user = User::factory()->create([
        'telephone' => '066555666',
        'password'  => 'pa55w0rd!',
        'google2fa_secret'       => $secret,
        'google2fa_confirmed_at' => now(),
    ]);
    $user->roles()->sync([Role::query()->where('code', 'de')->value('id')]);

    loginAndPreAuth($user);

    $otp = $google->getCurrentOtp($secret);

    $this->post('/two-factor/challenge', ['otp' => $otp])
        ->assertRedirect();

    $this->assertAuthenticatedAs($user->fresh());
});
