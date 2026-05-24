<?php

declare(strict_types=1);

namespace Modules\UserManagement\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Modules\UserManagement\Models\User;

/**
 * @extends Factory<User>
 */
final class UserFactory extends Factory
{
    protected $model = User::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'nom'             => mb_strtoupper($this->faker->lastName()),
            'prenom'          => $this->faker->firstName(),
            'email'           => $this->faker->unique()->safeEmail(),
            'telephone'       => $this->faker->unique()->numerify('06#######'),
            'password'        => Hash::make('pa55w0rd!'),
            'password_legacy' => false,
            'google2fa_secret' => null,
            'google2fa_confirmed_at' => null,
        ];
    }

    public function legacySha1(string $plain = 'pa55w0rd!'): static
    {
        return $this->state(fn (): array => [
            'password'        => sha1($plain),
            'password_legacy' => true,
        ]);
    }

    public function withTwoFactor(string $secret = 'JBSWY3DPEHPK3PXP'): static
    {
        return $this->state(fn (): array => [
            'google2fa_secret'       => $secret,
            'google2fa_confirmed_at' => now(),
        ]);
    }
}
