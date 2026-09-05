<?php

declare(strict_types=1);

namespace BunnySidecar\BunnySidecar\Database\Factories;

use BunnySidecar\BunnySidecar\Enums\UserRole;
use BunnySidecar\BunnySidecar\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    protected static ?string $password = null;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => static::$password ??= Hash::make('password'),
            'role' => UserRole::Member,
            'remember_token' => Str::random(10),
        ];
    }

    public function admin(): static
    {
        return $this->state(fn (array $attributes): array => ['role' => UserRole::Admin]);
    }

    public function member(): static
    {
        return $this->state(fn (array $attributes): array => ['role' => UserRole::Member]);
    }

    /**
     * A confirmed authenticator app with the recovery codes "recovery-code-1" to "recovery-code-8".
     */
    public function withTwoFactor(): static
    {
        return $this->state(fn (array $attributes): array => [
            'two_factor_secret' => app(Google2FA::class)->generateSecretKey(),
            'two_factor_recovery_codes' => array_map(fn (int $i): string => "recovery-code-{$i}", range(1, 8)),
            'two_factor_confirmed_at' => now(),
        ]);
    }
}
