<?php

declare(strict_types=1);

namespace BunnySidecar\BunnySidecar\Database\Factories;

use BunnySidecar\BunnySidecar\Models\BunnyAccount;
use BunnySidecar\BunnySidecar\Models\DnsRecordOwnership;
use BunnySidecar\BunnySidecar\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DnsRecordOwnership>
 */
class DnsRecordOwnershipFactory extends Factory
{
    protected $model = DnsRecordOwnership::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'bunny_account_id' => BunnyAccount::factory(),
            'zone_domain' => fake()->domainName(),
            'record_id' => fake()->unique()->numberBetween(1, 1_000_000),
            'created_by' => User::factory(),
        ];
    }
}
