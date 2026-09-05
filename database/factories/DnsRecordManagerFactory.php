<?php

declare(strict_types=1);

namespace BunnySidecar\BunnySidecar\Database\Factories;

use BunnySidecar\BunnySidecar\Models\BunnyAccount;
use BunnySidecar\BunnySidecar\Models\DnsRecordManager;
use BunnySidecar\BunnySidecar\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DnsRecordManager>
 */
class DnsRecordManagerFactory extends Factory
{
    protected $model = DnsRecordManager::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'bunny_account_id' => BunnyAccount::factory(),
            'zone_domain' => fake()->domainName(),
            'record_id' => fake()->numberBetween(1, 1_000_000),
            'user_id' => User::factory(),
        ];
    }
}
