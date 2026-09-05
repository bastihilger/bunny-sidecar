<?php

declare(strict_types=1);

namespace BunnySidecar\BunnySidecar\Database\Factories;

use BunnySidecar\BunnySidecar\Enums\UserRole;
use BunnySidecar\BunnySidecar\Models\Invitation;
use BunnySidecar\BunnySidecar\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Invitation>
 */
class InvitationFactory extends Factory
{
    protected $model = Invitation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'email' => fake()->unique()->safeEmail(),
            'name' => fake()->name(),
            'role' => UserRole::Member,
            'token' => hash('sha256', Str::random(64)),
            'invited_by' => User::factory()->admin(),
            'zone_grants' => [],
            'expires_at' => now()->addDays(Invitation::EXPIRES_AFTER_DAYS),
            'accepted_at' => null,
        ];
    }

    public function admin(): static
    {
        return $this->state(fn (array $attributes): array => [
            'role' => UserRole::Admin,
            'zone_grants' => null,
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes): array => [
            'expires_at' => now()->subDay(),
        ]);
    }

    public function accepted(): static
    {
        return $this->state(fn (array $attributes): array => [
            'accepted_at' => now(),
        ]);
    }
}
