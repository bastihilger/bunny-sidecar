<?php

declare(strict_types=1);

namespace BunnySidecar\BunnySidecar\Bunny\Dtos;

/**
 * A DNS zone as returned by the bunny.net API.
 */
final readonly class DnsZone
{
    /**
     * @param  list<DnsRecord>  $records
     */
    public function __construct(
        public int $id,
        public string $domain,
        public ?string $nameserver1 = null,
        public ?string $nameserver2 = null,
        public array $records = [],
    ) {}

    /**
     * A plain-array representation safe for cache stores that refuse to
     * unserialize objects.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            ...get_object_vars($this),
            'records' => array_map(fn (DnsRecord $record): array => $record->toArray(), $this->records),
        ];
    }

    /**
     * @param  array<string, mixed>  $data  the shape produced by toArray()
     */
    public static function fromArray(array $data): self
    {
        return new self(...[
            ...$data,
            'records' => array_map(DnsRecord::fromArray(...), $data['records']),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromApi(array $data): self
    {
        return new self(
            id: (int) $data['Id'],
            domain: (string) $data['Domain'],
            nameserver1: isset($data['Nameserver1']) && $data['Nameserver1'] !== '' ? (string) $data['Nameserver1'] : null,
            nameserver2: isset($data['Nameserver2']) && $data['Nameserver2'] !== '' ? (string) $data['Nameserver2'] : null,
            records: array_map(
                DnsRecord::fromApi(...),
                array_values($data['Records'] ?? []),
            ),
        );
    }
}
