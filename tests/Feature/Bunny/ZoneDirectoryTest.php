<?php

declare(strict_types=1);

use BunnySidecar\BunnySidecar\Bunny\Dtos\DnsRecord;
use BunnySidecar\BunnySidecar\Bunny\Dtos\DnsZone;
use BunnySidecar\BunnySidecar\Bunny\ZoneDirectory;
use BunnySidecar\BunnySidecar\Enums\DnsEntryType;
use BunnySidecar\BunnySidecar\Models\BunnyAccount;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    Http::preventStrayRequests();

    $this->directory = app(ZoneDirectory::class);
});

test('caches the zone list per account', function (): void {
    $account = BunnyAccount::factory()->create();
    Http::fake(['api.bunny.net/dnszone*' => Http::response(bunnyPageFixture([bunnyZoneFixture()]))]);

    $this->directory->zonesFor($account);
    $this->directory->zonesFor($account);

    Http::assertSentCount(1);
});

test('forgetZones busts the cache', function (): void {
    $account = BunnyAccount::factory()->create();
    Http::fake(['api.bunny.net/dnszone*' => Http::response(bunnyPageFixture([bunnyZoneFixture()]))]);

    $this->directory->zonesFor($account);
    $this->directory->forgetZones($account);
    $this->directory->zonesFor($account);

    Http::assertSentCount(2);
});

test('accounts are cached independently', function (): void {
    $first = BunnyAccount::factory()->create(['api_key' => 'first-key']);
    $second = BunnyAccount::factory()->create(['api_key' => 'second-key']);

    Http::fake(fn (Request $request) => $request->header('AccessKey') === ['first-key']
        ? Http::response(bunnyPageFixture([bunnyZoneFixture(['Domain' => 'first.com'])]))
        : Http::response(bunnyPageFixture([bunnyZoneFixture(['Domain' => 'second.com'])])));

    expect($this->directory->zonesFor($first)->first()?->domain)->toBe('first.com')
        ->and($this->directory->zonesFor($second)->first()?->domain)->toBe('second.com');
});

test('all aggregates the zones of every account with their account', function (): void {
    BunnyAccount::factory()->create(['name' => 'Alpha']);
    BunnyAccount::factory()->create(['name' => 'Beta']);
    Http::fake(['api.bunny.net/dnszone*' => Http::response(bunnyPageFixture([bunnyZoneFixture()]))]);

    $all = $this->directory->all();

    expect($all)->toHaveCount(2)
        ->and($all->first()['account']->name)->toBe('Alpha')
        ->and($all->first()['zone']->domain)->toBe('example.com');
});

test('find matches domains case-insensitively', function (): void {
    $account = BunnyAccount::factory()->create();
    Http::fake(['api.bunny.net/dnszone*' => Http::response(bunnyPageFixture([bunnyZoneFixture(['Domain' => 'Example.COM'])]))]);

    expect($this->directory->find($account, 'example.com')?->id)->toBe(1001)
        ->and($this->directory->find($account, 'missing.com'))->toBeNull();
});

test('caches plain arrays so any store can hold them and rebuilds dtos on read', function (): void {
    // The file store serializes like production stores do; hosts may forbid
    // unserializing objects, so only arrays may ever be cached.
    config(['cache.default' => 'file']);
    Cache::flush();

    $account = BunnyAccount::factory()->create();
    Http::fake([
        'api.bunny.net/dnszone?*' => Http::response(bunnyPageFixture([bunnyZoneFixture()])),
        'api.bunny.net/dnszone/1001' => Http::response(bunnyZoneFixture(['Records' => [bunnyRecordFixture()]])),
    ]);

    $this->directory->zonesFor($account);
    $this->directory->zoneWithRecords($account, 1001);

    expect(Cache::get("bunny-sidecar.zones.{$account->id}"))->toBeArray()
        ->and(Cache::get("bunny-sidecar.zone.{$account->id}.1001"))->toBeArray();

    $zones = app(ZoneDirectory::class)->zonesFor($account);
    $zone = app(ZoneDirectory::class)->zoneWithRecords($account, 1001);

    expect($zones->first())->toBeInstanceOf(DnsZone::class)
        ->and($zones->first()?->domain)->toBe('example.com')
        ->and($zone->records[0])->toBeInstanceOf(DnsRecord::class)
        ->and($zone->records[0]->type)->toBe(DnsEntryType::A);

    Http::assertSentCount(2);

    Cache::flush();
});

test('caches a single zone with records briefly and busts it after mutations', function (): void {
    $account = BunnyAccount::factory()->create();
    Http::fake(['api.bunny.net/dnszone/1001' => Http::response(bunnyZoneFixture(['Records' => [bunnyRecordFixture()]]))]);

    $this->directory->zoneWithRecords($account, 1001);
    $this->directory->zoneWithRecords($account, 1001);
    Http::assertSentCount(1);

    $this->directory->forgetRecords($account, 1001);
    $zone = $this->directory->zoneWithRecords($account, 1001);
    Http::assertSentCount(2);

    expect($zone->records)->toHaveCount(1);
});
