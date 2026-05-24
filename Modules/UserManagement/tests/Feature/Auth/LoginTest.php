<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\UserManagement\Models\Role;
use Modules\UserManagement\Models\User;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(Modules\UserManagement\Database\Seeders\RoleSeeder::class);
});

it('shows the login form on GET /login', function (): void {
    $this->get('/login')
        ->assertOk()
        ->assertSee('Connectez-vous');
});

it('rejects empty credentials', function (): void {
    $this->post('/login', ['identifier' => '', 'password' => ''])
        ->assertSessionHasErrors(['identifier', 'password']);
});

it('rejects an unknown identifier with the generic message', function (): void {
    $this->post('/login', [
        'identifier' => 'nope@example.com',
        'password'   => 'whatever',
    ])->assertSessionHasErrors('identifier');

    expect(session('errors')->first('identifier'))->toContain('Identifiants invalides');
});

it('rejects the wrong password', function (): void {
    User::factory()->create([
        'email'    => 'admin@cuk.ga',
        'password' => 'correct-password',
    ]);

    $this->post('/login', [
        'identifier' => 'admin@cuk.ga',
        'password'   => 'wrong-one',
    ])->assertSessionHasErrors('identifier');
});

it('accepts a valid bcrypt password and redirects to 2FA challenge for force-2FA roles', function (): void {
    $user = User::factory()->create([
        'telephone' => '066123456',
        'password'  => 'super-secret',
        'google2fa_secret'       => 'JBSWY3DPEHPK3PXP',
        'google2fa_confirmed_at' => now(),
    ]);
    $user->roles()->sync([Role::query()->where('code', 'dg')->value('id')]);

    $this->post('/login', [
        'identifier' => '066123456',
        'password'   => 'super-secret',
    ])->assertRedirect('/two-factor/challenge');
});

it('upgrades a legacy SHA1 password to bcrypt on first successful login', function (): void {
    $plain = 'old-password';
    $user = User::factory()->create([
        'telephone'       => '077999000',
        'password'        => sha1($plain),
        'password_legacy' => true,
    ]);
    $user->roles()->sync([Role::query()->where('code', 'candidat')->value('id')]);

    $this->post('/login', [
        'identifier' => '077999000',
        'password'   => $plain,
    ])->assertRedirect();

    $user->refresh();
    expect($user->password_legacy)->toBeFalse()
        ->and(strlen($user->password))->toBe(60); // bcrypt
});
