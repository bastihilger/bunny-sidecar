<?php

declare(strict_types=1);

namespace BunnySidecar\BunnySidecar\Bunny\Dtos;

use BunnySidecar\BunnySidecar\Enums\DnsEntryType;
use BunnySidecar\BunnySidecar\Enums\DnsRecordScope;

/**
 * A DNS record as returned by the bunny.net API.
 */
final readonly class DnsRecord
{
    public function __construct(
        public int $id,
        public DnsEntryType $type,
        public string $name,
        public string $value,
        public int $ttl,
        public ?int $priority = null,
        public ?int $weight = null,
        public ?int $port = null,
        public int $flags = 0,
        public ?string $tag = null,
        public ?int $pullZoneId = null,
        public ?int $scriptId = null,
        public bool $accelerated = false,
        public bool $disabled = false,
        public ?string $comment = null,
        public ?int $monitorType = null,
        public ?int $smartRoutingType = null,
        public ?float $geolocationLatitude = null,
        public ?float $geolocationLongitude = null,
        public ?string $latencyZone = null,
        public bool $autoSslIssuance = false,
    ) {}

    public function scope(): DnsRecordScope
    {
        return DnsRecordScope::fromName($this->name);
    }

    /**
     * A plain-array representation safe for cache stores that refuse to
     * unserialize objects.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [...get_object_vars($this), 'type' => $this->type->value];
    }

    /**
     * @param  array<string, mixed>  $data  the shape produced by toArray()
     */
    public static function fromArray(array $data): self
    {
        return new self(...[...$data, 'type' => DnsEntryType::from($data['type'])]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromApi(array $data): self
    {
        return new self(
            id: (int) $data['Id'],
            type: DnsEntryType::from((int) $data['Type']),
            name: (string) ($data['Name'] ?? ''),
            value: (string) ($data['Value'] ?? ''),
            ttl: (int) ($data['Ttl'] ?? 300),
            priority: isset($data['Priority']) ? (int) $data['Priority'] : null,
            weight: isset($data['Weight']) ? (int) $data['Weight'] : null,
            port: isset($data['Port']) ? (int) $data['Port'] : null,
            flags: (int) ($data['Flags'] ?? 0),
            tag: isset($data['Tag']) && $data['Tag'] !== '' ? (string) $data['Tag'] : null,
            pullZoneId: isset($data['PullZoneId']) && (int) $data['PullZoneId'] > 0 ? (int) $data['PullZoneId'] : null,
            scriptId: isset($data['ScriptId']) && (int) $data['ScriptId'] > 0 ? (int) $data['ScriptId'] : null,
            accelerated: (bool) ($data['Accelerated'] ?? false),
            disabled: (bool) ($data['Disabled'] ?? false),
            comment: isset($data['Comment']) && $data['Comment'] !== '' ? (string) $data['Comment'] : null,
            monitorType: isset($data['MonitorType']) ? (int) $data['MonitorType'] : null,
            smartRoutingType: isset($data['SmartRoutingType']) ? (int) $data['SmartRoutingType'] : null,
            geolocationLatitude: isset($data['GeolocationLatitude']) ? (float) $data['GeolocationLatitude'] : null,
            geolocationLongitude: isset($data['GeolocationLongitude']) ? (float) $data['GeolocationLongitude'] : null,
            latencyZone: isset($data['LatencyZone']) && $data['LatencyZone'] !== '' ? (string) $data['LatencyZone'] : null,
            autoSslIssuance: (bool) ($data['AutoSslIssuance'] ?? false),
        );
    }
}
