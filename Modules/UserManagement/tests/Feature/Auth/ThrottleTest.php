<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Modules\UserManagement\Models\User;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(Modules\UserManagement\Database\Seeders\RoleSeeder::class);
    config()->set('usermanagement.throttle.fast.max_attempts', 3);
    config()->set('usermanagement.throttle.fast.decay_seconds', 900);
    config()->set('usermanagement.throttle.slow.max_attempts', 5);
    config()->set('usermanagement.throttle.slow.decay_seconds', 86400);
});

it('locks the account after 3 failed attempts (fast tier)', function (): void {
    User::factory()->create(['telephone' => '066000111', 'password' => 'correct']);

    for ($i = 1; $i <= 3; $i++) {
        $this->post('/login', ['identifier' => '066000111', 'password' => 'wrong']);
    }

    $this->post('/login', ['identifier' => '066000111', 'password' => 'correct'])
        ->assertSessionHasErrors('identifier');

    expect(session('errors')->first('identifier'))->toContain('Trop de tentatives');
});

it('clears throttle on successful login', function (): void {
    $user = User::factory()->create([
        'telephone' => '066222333',
        'password'  => 'correct',
    ]);
    $user->roles()->sync([
        Modules\UserManagement\Models\Role::query()->where('code', 'candidat')->value('id'),
    ]);

    $this->post('/login', ['identifier' => '066222333', 'password' => 'wrong']);
    $this->post('/login', ['identifier' => '066222333', 'password' => 'wrong']);

    // Successful login (candidat role: no 2FA enforced)
    $this->post('/login', ['identifier' => '066222333', 'password' => 'correct'])->assertRedirect();

    // After success, no throttling residue → another wrong attempt does not immediately lock
    $this->post('/logout');
    $this->post('/login', ['identifier' => '066222333', 'password' => 'wrong'])
        ->assertSessionHasErrors('identifier');
    expect(session('errors')->first('identifier'))->toContain('Identifiants invalides');
});
