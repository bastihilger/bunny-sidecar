<?php

declare(strict_types=1);

namespace BunnySidecar\BunnySidecar\Database\Factories;

use BunnySidecar\BunnySidecar\Dtos\ZoneGrantPermissions;
use BunnySidecar\BunnySidecar\Enums\DnsEntryType;
use BunnySidecar\BunnySidecar\Enums\DnsRecordScope;
use BunnySidecar\BunnySidecar\Models\BunnyAccount;
use BunnySidecar\BunnySidecar\Models\DnsZoneGrant;
use BunnySidecar\BunnySidecar\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DnsZoneGrant>
 */
class DnsZoneGrantFactory extends Factory
{
    protected $model = DnsZoneGrant::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'bunny_account_id' => BunnyAccount::factory(),
            'zone_domain' => fake()->domainName(),
            'permissions' => ZoneGrantPermissions::none(),
        ];
    }

    /**
     * Allow adding every record type in both scopes.
     */
    public function allowAll(): static
    {
        return $this->allow();
    }

    /**
     * Allow adding the given record types (or all types when none given) in both scopes.
     */
    public function allow(DnsEntryType ...$types): static
    {
        $state = $this;

        foreach (DnsRecordScope::cases() as $scope) {
            $state = $state->allowIn($scope, ...$types);
        }

        return $state;
    }

    /**
     * Allow adding the given record types (or all types when none given) in one scope.
     */
    public function allowIn(DnsRecordScope $scope, DnsEntryType ...$types): static
    {
        return $this->state(function (array $attributes) use ($scope, $types): array {
            $permissions = $attributes['permissions'] instanceof ZoneGrantPermissions
                ? $attributes['permissions']->toArray()
                : (array) $attributes['permissions'];

            $permissions[$scope->value] = $types === []
                ? ZoneGrantPermissions::ALL_TYPES
                : array_map(fn (DnsEntryType $type): int => $type->value, $types);

            return ['permissions' => ZoneGrantPermissions::fromArray($permissions)];
        });
    }
}
