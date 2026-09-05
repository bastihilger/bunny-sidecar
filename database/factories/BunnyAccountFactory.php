<?php

declare(strict_types=1);

namespace BunnySidecar\BunnySidecar\Database\Factories;

use BunnySidecar\BunnySidecar\Models\BunnyAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BunnyAccount>
 */
class BunnyAccountFactory extends Factory
{
    protected $model = BunnyAccount::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'api_key' => fake()->uuid(),
        ];
    }
}
