<?php

declare(strict_types=1);

namespace BunnySidecar\BunnySidecar\Bunny\Dtos;

/**
 * A bunny.net Shield (WAF) zone. The API only links it to a pull zone by id,
 * so the pull zone's name and hostnames are merged in to get a readable label.
 */
final readonly class ShieldZone
{
    /**
     * @param  list<string>  $hostnames
     */
    public function __construct(
        public int $id,
        public int $pullZoneId,
        public ?string $pullZoneName = null,
        public array $hostnames = [],
    ) {}

    public function withPullZone(?PullZone $pullZone): self
    {
        if ($pullZone === null) {
            return $this;
        }

        return new self($this->id, $this->pullZoneId, $pullZone->name, $pullZone->hostnames);
    }

    /**
     * A human readable label: the first custom hostname, else the pull zone
     * name, else the bare shield zone id.
     */
    public function label(): string
    {
        if ($this->hostnames !== []) {
            return $this->hostnames[0];
        }

        return $this->pullZoneName ?? "Shield zone #{$this->id}";
    }

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
        return new self(
            id: (int) $data['shieldZoneId'],
            pullZoneId: (int) $data['pullZoneId'],
        );
    }
}
