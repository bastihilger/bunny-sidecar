<?php

declare(strict_types=1);

namespace BunnySidecar\BunnySidecar\Bunny;

use BunnySidecar\BunnySidecar\Bunny\Dtos\PullZone;
use BunnySidecar\BunnySidecar\Bunny\Dtos\ShieldEvent;
use BunnySidecar\BunnySidecar\Bunny\Dtos\ShieldRuleGroup;
use BunnySidecar\BunnySidecar\Bunny\Dtos\ShieldZone;
use BunnySidecar\BunnySidecar\Models\BunnyAccount;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Cached access to the Shield (WAF) zones of all bunny accounts and their
 * event logs. Shield zone lists are cached like DNS zone lists; event
 * searches are never cached.
 */
class ShieldDirectory
{
    /**
     * Shield zones across all bunny accounts, labelled and sorted.
     *
     * @return Collection<int, array{account: BunnyAccount, zone: ShieldZone}>
     */
    public function all(): Collection
    {
        return BunnyAccount::query()
            ->orderBy('name')
            ->get()
            ->flatMap(fn (BunnyAccount $account): Collection => $this->shieldZonesFor($account)
                ->map(fn (ShieldZone $zone): array => ['account' => $account, 'zone' => $zone]))
            ->sortBy(fn (array $row): string => mb_strtolower($row['zone']->label()))
            ->values();
    }

    /**
     * The shield zones of one account with their pull zone labels, cached.
     *
     * @return Collection<int, ShieldZone>
     */
    public function shieldZonesFor(BunnyAccount $account): Collection
    {
        // Cache plain arrays, not DTOs: hosts may disable object unserialization.
        $zones = Cache::remember(
            $this->cacheKey($account),
            config()->integer('bunny-sidecar.api.zones_ttl'),
            function () use ($account): array {
                $client = BunnyClient::for($account);
                $pullZones = $client->pullZones()->keyBy(fn (PullZone $pullZone): int => $pullZone->id);

                return $client->shieldZones()
                    ->map(fn (ShieldZone $zone): array => $zone->withPullZone($pullZones->get($zone->pullZoneId))->toArray())
                    ->all();
            },
        );

        return collect($zones)->map(ShieldZone::fromArray(...));
    }

    public function find(BunnyAccount $account, int $shieldZoneId): ?ShieldZone
    {
        return $this->shieldZonesFor($account)
            ->first(fn (ShieldZone $zone): bool => $zone->id === $shieldZoneId);
    }

    public function forgetShieldZones(BunnyAccount $account): void
    {
        Cache::forget($this->cacheKey($account));
    }

    /**
     * All events of one WAF rule in the given window, newest first. Pages
     * through the search up to the configured maximum number of pages.
     * The search query is free text, so events are re-filtered by rule id.
     *
     * @return Collection<int, ShieldEvent>
     */
    public function eventsForRule(BunnyAccount $account, int $shieldZoneId, string $ruleId, CarbonInterface $from, CarbonInterface $to): Collection
    {
        $client = BunnyClient::for($account);
        $events = collect();
        $page = 0;

        do {
            $result = $client->searchShieldEvents(
                $shieldZoneId,
                $from->getTimestampMs(),
                $to->getTimestampMs(),
                $ruleId,
                $page,
                config()->integer('bunny-sidecar.api.shield_events_page_size'),
            );

            $events = $events->merge($result->events);
            $page++;
        } while ($result->hasMorePages() && $page < config()->integer('bunny-sidecar.api.shield_events_max_pages'));

        return $events
            ->filter(fn (ShieldEvent $event): bool => $event->ruleId === null || $event->ruleId === $ruleId)
            ->sortByDesc(fn (ShieldEvent $event): int => $event->timestamp)
            ->values();
    }

    /**
     * The WAF rules that fired on a shield zone in the window, most frequent
     * first. Briefly cached per zone.
     *
     * @return Collection<int, ShieldRuleGroup>
     */
    public function rulesFor(BunnyAccount $account, int $shieldZoneId, CarbonInterface $from, CarbonInterface $to): Collection
    {
        $groups = Cache::remember(
            $this->rulesCacheKey($account, $shieldZoneId),
            config()->integer('bunny-sidecar.api.shield_rules_ttl'),
            fn (): array => BunnyClient::for($account)
                ->shieldRuleGroups($shieldZoneId, $from->getTimestampMs(), $to->getTimestampMs())
                ->map(fn (ShieldRuleGroup $group): array => $group->toArray())
                ->all(),
        );

        return collect($groups)->map(ShieldRuleGroup::fromArray(...));
    }

    public function forgetRules(BunnyAccount $account, int $shieldZoneId): void
    {
        Cache::forget($this->rulesCacheKey($account, $shieldZoneId));
    }

    private function cacheKey(BunnyAccount $account): string
    {
        return "bunny-sidecar.shield-zones.{$account->id}";
    }

    private function rulesCacheKey(BunnyAccount $account, int $shieldZoneId): string
    {
        return "bunny-sidecar.shield-rules.{$account->id}.{$shieldZoneId}";
    }
}
