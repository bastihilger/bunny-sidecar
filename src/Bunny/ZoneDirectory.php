<?php

declare(strict_types=1);

namespace BunnySidecar\BunnySidecar\Bunny;

use BunnySidecar\BunnySidecar\Bunny\Dtos\DnsZone;
use BunnySidecar\BunnySidecar\Models\BunnyAccount;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Cached access to the DNS zones of all configured bunny accounts. Zone
 * lists are cached for a few minutes, single zones (with records) only very
 * briefly to soften bunny.net rate limits.
 */
class ZoneDirectory
{
    /**
     * Zones across all bunny accounts.
     *
     * @return Collection<int, array{account: BunnyAccount, zone: DnsZone}>
     */
    public function all(): Collection
    {
        return BunnyAccount::query()
            ->orderBy('name')
            ->get()
            ->flatMap(fn (BunnyAccount $account): Collection => $this->zonesFor($account)
                ->map(fn (DnsZone $zone): array => ['account' => $account, 'zone' => $zone]))
            ->values();
    }

    /**
     * The zones of one account (without records), cached.
     *
     * @return Collection<int, DnsZone>
     */
    public function zonesFor(BunnyAccount $account): Collection
    {
        // Cache plain arrays, not DTOs: hosts may disable object unserialization.
        $zones = Cache::remember(
            $this->zonesCacheKey($account),
            config()->integer('bunny-sidecar.api.zones_ttl'),
            fn (): array => BunnyClient::for($account)->zones()
                ->map(fn (DnsZone $zone): array => $zone->toArray())
                ->all(),
        );

        return collect($zones)->map(DnsZone::fromArray(...));
    }

    public function find(BunnyAccount $account, string $domain): ?DnsZone
    {
        return $this->zonesFor($account)
            ->first(fn (DnsZone $zone): bool => mb_strtolower($zone->domain) === mb_strtolower($domain));
    }

    /**
     * A single zone including its records, briefly cached.
     */
    public function zoneWithRecords(BunnyAccount $account, int $zoneId): DnsZone
    {
        $zone = Cache::remember(
            $this->zoneCacheKey($account, $zoneId),
            config()->integer('bunny-sidecar.api.records_ttl'),
            fn (): array => BunnyClient::for($account)->zone($zoneId)->toArray(),
        );

        return DnsZone::fromArray($zone);
    }

    public function forgetZones(BunnyAccount $account): void
    {
        Cache::forget($this->zonesCacheKey($account));
    }

    public function forgetRecords(BunnyAccount $account, int $zoneId): void
    {
        Cache::forget($this->zoneCacheKey($account, $zoneId));
    }

    private function zonesCacheKey(BunnyAccount $account): string
    {
        return "bunny-sidecar.zones.{$account->id}";
    }

    private function zoneCacheKey(BunnyAccount $account, int $zoneId): string
    {
        return "bunny-sidecar.zone.{$account->id}.{$zoneId}";
    }
}
