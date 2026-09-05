<?php

declare(strict_types=1);

namespace BunnySidecar\BunnySidecar\Dtos;

use BunnySidecar\BunnySidecar\Enums\DnsEntryType;
use BunnySidecar\BunnySidecar\Enums\DnsRecordScope;
use Illuminate\Contracts\Database\Eloquent\Castable;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use JsonSerializable;

/**
 * The record types a member may add in a zone, per scope (zone root vs.
 * subdomains): either all types ('*'), a subset, or nothing. Adding also
 * implies editing and deleting the records the member added themselves;
 * anything beyond that is an explicit per-record manager assignment.
 *
 * Serialized shape: {"root": "*"|[0,3], "subdomain": "*"|[0,3]} where the
 * integers are DnsEntryType backing values.
 *
 * @implements Arrayable<string, '*'|list<int>>
 */
final readonly class ZoneGrantPermissions implements Arrayable, Castable, JsonSerializable
{
    public const string ALL_TYPES = '*';

    /**
     * @param  array<string, '*'|list<int>>  $scopes  keyed by DnsRecordScope value
     */
    private function __construct(private array $scopes) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $scopes = [];

        foreach (DnsRecordScope::cases() as $scope) {
            $value = $data[$scope->value] ?? [];

            if ($value === self::ALL_TYPES) {
                $scopes[$scope->value] = self::ALL_TYPES;

                continue;
            }

            if (! is_array($value)) {
                throw new InvalidArgumentException("Permissions for [{$scope->value}] must be '*' or a list of DNS entry type values.");
            }

            $types = array_map(
                fn (mixed $type): int => DnsEntryType::from(is_int($type) ? $type : (int) $type)->value,
                array_values($value),
            );
            sort($types);

            $scopes[$scope->value] = array_values(array_unique($types));
        }

        return new self($scopes);
    }

    public static function none(): self
    {
        return self::fromArray([]);
    }

    public function allows(DnsRecordScope $scope, DnsEntryType $type): bool
    {
        $allowed = $this->scopes[$scope->value];

        return $allowed === self::ALL_TYPES || in_array($type->value, $allowed, true);
    }

    public function allowsAll(DnsRecordScope $scope): bool
    {
        return $this->scopes[$scope->value] === self::ALL_TYPES;
    }

    public function allowsAny(DnsRecordScope $scope): bool
    {
        return $this->scopes[$scope->value] !== [];
    }

    /**
     * Whether at least one type may be added in at least one scope.
     */
    public function allowsAnywhere(): bool
    {
        foreach (DnsRecordScope::cases() as $scope) {
            if ($this->allowsAny($scope)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The record types allowed in the given scope.
     *
     * @return list<DnsEntryType>
     */
    public function typesFor(DnsRecordScope $scope): array
    {
        if ($this->allowsAll($scope)) {
            return DnsEntryType::cases();
        }

        /** @var list<int> $allowed */
        $allowed = $this->scopes[$scope->value];

        return array_map(DnsEntryType::from(...), $allowed);
    }

    /**
     * @return array<string, '*'|list<int>>
     */
    public function toArray(): array
    {
        return $this->scopes;
    }

    /**
     * @return array<string, '*'|list<int>>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return CastsAttributes<self, self|array<string, mixed>>
     */
    public static function castUsing(array $arguments): CastsAttributes
    {
        /** @implements CastsAttributes<ZoneGrantPermissions, ZoneGrantPermissions|array<string, mixed>> */
        return new class implements CastsAttributes
        {
            public function get(Model $model, string $key, mixed $value, array $attributes): ZoneGrantPermissions
            {
                return ZoneGrantPermissions::fromArray(
                    is_string($value) ? json_decode($value, true, flags: JSON_THROW_ON_ERROR) : [],
                );
            }

            public function set(Model $model, string $key, mixed $value, array $attributes): string
            {
                if (is_array($value)) {
                    $value = ZoneGrantPermissions::fromArray($value);
                }

                if (! $value instanceof ZoneGrantPermissions) {
                    throw new InvalidArgumentException('Permissions must be a ZoneGrantPermissions instance or array.');
                }

                return json_encode($value, JSON_THROW_ON_ERROR);
            }
        };
    }
}
