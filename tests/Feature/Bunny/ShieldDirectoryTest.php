<?php

declare(strict_types=1);

use BunnySidecar\BunnySidecar\Bunny\Dtos\ShieldZone;
use BunnySidecar\BunnySidecar\Bunny\ShieldDirectory;
use BunnySidecar\BunnySidecar\Models\BunnyAccount;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    Http::preventStrayRequests();

    $this->directory = app(ShieldDirectory::class);
    $this->account = BunnyAccount::factory()->create();
});

test('labels shield zones with the first custom hostname of their pull zone', function (): void {
    Http::fake([
        'api.bunny.net/pullzone*' => Http::response(bunnyPageFixture([
            bunnyPullZoneFixture(['Id' => 7001, 'Hostnames' => [
                ['Value' => 'x.b-cdn.net', 'IsSystemHostname' => true],
                ['Value' => 'app.example.com', 'IsSystemHostname' => false],
                ['Value' => 'www.app.example.com', 'IsSystemHostname' => false],
            ]]),
            bunnyPullZoneFixture(['Id' => 7002, 'Name' => 'bare-pullzone', 'Hostnames' => [['Value' => 'bare.b-cdn.net', 'IsSystemHostname' => true]]]),
        ])),
        'api.bunny.net/shield/shield-zones*' => Http::response(bunnyShieldPageFixture([
            bunnyShieldZoneFixture(['shieldZoneId' => 1, 'pullZoneId' => 7001]),
            bunnyShieldZoneFixture(['shieldZoneId' => 2, 'pullZoneId' => 7002]),
            bunnyShieldZoneFixture(['shieldZoneId' => 3, 'pullZoneId' => 9999]),
        ])),
    ]);

    $labels = $this->directory->shieldZonesFor($this->account)->mapWithKeys(fn (ShieldZone $zone): array => [$zone->id => $zone->label()]);

    expect($labels->all())->toBe([1 => 'app.example.com', 2 => 'bare-pullzone', 3 => 'Shield zone #3']);
});

test('caches shield zones per account as plain arrays', function (): void {
    config(['cache.default' => 'file']);
    Cache::flush();
    Http::fake([
        'api.bunny.net/pullzone*' => Http::response(bunnyPageFixture([bunnyPullZoneFixture()])),
        'api.bunny.net/shield/shield-zones*' => Http::response(bunnyShieldPageFixture([bunnyShieldZoneFixture()])),
    ]);

    $this->directory->shieldZonesFor($this->account);
    $zones = $this->directory->shieldZonesFor($this->account);

    Http::assertSentCount(2);
    expect(Cache::get("bunny-sidecar.shield-zones.{$this->account->id}"))->toBeArray()
        ->and($zones->first())->toBeInstanceOf(ShieldZone::class)
        ->and($zones->first()?->label())->toBe('app.example.com')
        ->and($this->directory->find($this->account, 3001)?->id)->toBe(3001);

    $this->directory->forgetShieldZones($this->account);
    $this->directory->shieldZonesFor($this->account);

    Http::assertSentCount(4);

    Cache::flush();
});

test('collects every page of events for a rule and drops other rules', function (): void {
    config(['bunny-sidecar.api.shield_events_page_size' => 2, 'bunny-sidecar.api.shield_events_max_pages' => 10]);
    Http::fake(function (Request $request) {
        $page = $request->data()['page'];

        return Http::response(match ($page) {
            0 => bunnyShieldSearchFixture([
                bunnyShieldEventFixture(logId: 'a', timestamp: 100),
                bunnyShieldEventFixture(['ruleId' => '932130'], logId: 'other', timestamp: 90),
            ], page: 0, totalPages: 2),
            default => bunnyShieldSearchFixture([bunnyShieldEventFixture(logId: 'b', timestamp: 200)], page: 1, totalPages: 2),
        });
    });

    $from = now()->subHours(48);
    $to = now();

    $events = $this->directory->eventsForRule($this->account, 3001, '942550', $from, $to);

    expect($events->pluck('logId')->all())->toBe(['b', 'a']);

    Http::assertSentCount(2);
    Http::assertSent(fn (Request $request): bool => $request->data()['from'] === $from->getTimestampMs()
        && $request->data()['to'] === $to->getTimestampMs()
        && $request->data()['pageSize'] === 2
        && str_ends_with($request->url(), '/shield/event-logs/3001/search'));
});

test('stops paging at the configured maximum', function (): void {
    config(['bunny-sidecar.api.shield_events_max_pages' => 2]);
    Http::fake(fn (Request $request) => Http::response(bunnyShieldSearchFixture(
        [bunnyShieldEventFixture(logId: 'page-'.$request->data()['page'])],
        page: $request->data()['page'],
        totalPages: 50,
    )));

    $events = $this->directory->eventsForRule($this->account, 3001, '942550', now()->subDay(), now());

    expect($events)->toHaveCount(2);
    Http::assertSentCount(2);
});

test('caches the fired rules per shield zone as plain arrays', function (): void {
    config(['cache.default' => 'file']);
    Cache::flush();
    Http::fake(['api.bunny.net/shield/event-logs/*' => Http::response(bunnyShieldGroupsFixture([bunnyShieldRuleGroupFixture()]))]);

    $this->directory->rulesFor($this->account, 3001, now()->subHours(72), now());
    $rules = $this->directory->rulesFor($this->account, 3001, now()->subHours(72), now());

    Http::assertSentCount(1);
    expect(Cache::get("bunny-sidecar.shield-rules.{$this->account->id}.3001"))->toBeArray()
        ->and($rules->first()?->ruleId)->toBe('942550')
        ->and($rules->first()?->count)->toBe(77);

    $this->directory->forgetRules($this->account, 3001);
    $this->directory->rulesFor($this->account, 3001, now()->subHours(72), now());

    Http::assertSentCount(2);

    Cache::flush();
});
