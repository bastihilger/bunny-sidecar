<?php

declare(strict_types=1);

namespace BunnySidecar\BunnySidecar\Bunny\Dtos;

/**
 * A CDN pull zone as returned by the bunny.net API, reduced to what is
 * needed to label its shield zone.
 */
final readonly class PullZone
{
    /**
     * @param  list<string>  $hostnames  custom (non-system) hostnames
     */
    public function __construct(
        public int $id,
        public string $name,
        public array $hostnames = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return get_object_vars($this);
    }

    /**
     * @param  array<string, mixed>  $data  the shape produced by toArray()
     */
    public static function fromArray(array $data): self
    {
        return new self(...$data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromApi(array $data): self
    {
        $hostnames = [];

        foreach ((array) ($data['Hostnames'] ?? []) as $hostname) {
            if (! is_array($hostname) || (bool) ($hostname['IsSystemHostname'] ?? false)) {
                continue;
            }

            $value = $hostname['Value'] ?? null;

            if (is_string($value) && $value !== '') {
                $hostnames[] = $value;
            }
        }

        return new self(
            id: (int) $data['Id'],
            name: (string) $data['Name'],
            hostnames: $hostnames,
        );
    }
}
