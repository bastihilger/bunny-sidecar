<?php

declare(strict_types=1);

use BunnySidecar\BunnySidecar\Bunny\BunnyApiException;
use BunnySidecar\BunnySidecar\Bunny\BunnyClient;
use BunnySidecar\BunnySidecar\Enums\DnsEntryType;
use BunnySidecar\BunnySidecar\Models\BunnyAccount;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    Http::preventStrayRequests();

    $this->account = BunnyAccount::factory()->create(['api_key' => 'secret-key']);
});

test('sends the decrypted api key as AccessKey header', function (): void {
    Http::fake(['api.bunny.net/dnszone*' => Http::response(bunnyPageFixture([bunnyZoneFixture()]))]);

    BunnyClient::for($this->account)->zones();

    Http::assertSent(fn (Request $request): bool => $request->header('AccessKey') === ['secret-key']);
});

test('uses the configured api base url', function (): void {
    config(['bunny-sidecar.api.base_url' => 'https://bunny.example.test']);
    Http::fake(['bunny.example.test/dnszone*' => Http::response(bunnyPageFixture([]))]);

    BunnyClient::for($this->account)->zones();

    Http::assertSent(fn (Request $request): bool => str_starts_with($request->url(), 'https://bunny.example.test/dnszone'));
});

test('paginates through all zone pages', function (): void {
    Http::fake([
        'api.bunny.net/dnszone?page=1*' => Http::response(bunnyPageFixture([bunnyZoneFixture(['Id' => 1, 'Domain' => 'one.com'])], hasMore: true)),
        'api.bunny.net/dnszone?page=2*' => Http::response(bunnyPageFixture([bunnyZoneFixture(['Id' => 2, 'Domain' => 'two.com'])])),
    ]);

    $zones = BunnyClient::for($this->account)->zones();

    expect($zones)->toHaveCount(2)
        ->and($zones->pluck('domain')->all())->toBe(['one.com', 'two.com']);
});

test('maps a zone including records to dtos', function (): void {
    Http::fake([
        'api.bunny.net/dnszone/1001' => Http::response(bunnyZoneFixture([
            'Records' => [
                bunnyRecordFixture(['Id' => 1, 'Type' => DnsEntryType::MX->value, 'Name' => '', 'Value' => 'mail.example.com', 'Priority' => 10]),
            ],
        ])),
    ]);

    $zone = BunnyClient::for($this->account)->zone(1001);

    expect($zone->id)->toBe(1001)
        ->and($zone->domain)->toBe('example.com')
        ->and($zone->records)->toHaveCount(1)
        ->and($zone->records[0]->type)->toBe(DnsEntryType::MX)
        ->and($zone->records[0]->priority)->toBe(10);
});

test('creates, updates and deletes records against the right endpoints', function (): void {
    Http::fake([
        'api.bunny.net/dnszone/1001/records' => Http::response(bunnyRecordFixture()),
        'api.bunny.net/dnszone/1001/records/5001' => Http::response(status: 204),
    ]);

    $client = BunnyClient::for($this->account);

    $record = $client->createRecord(1001, ['Type' => 0, 'Name' => 'www', 'Value' => '192.0.2.10', 'Ttl' => 300]);
    $client->updateRecord(1001, 5001, ['Type' => 0, 'Name' => 'www', 'Value' => '192.0.2.20', 'Ttl' => 300]);
    $client->deleteRecord(1001, 5001);

    expect($record->id)->toBe(5001);

    Http::assertSent(fn (Request $request): bool => $request->method() === 'PUT'
        && str_ends_with($request->url(), '/dnszone/1001/records')
        && $request->data()['Value'] === '192.0.2.10');
    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
        && str_ends_with($request->url(), '/dnszone/1001/records/5001')
        && $request->data()['Value'] === '192.0.2.20');
    Http::assertSent(fn (Request $request): bool => $request->method() === 'DELETE'
        && str_ends_with($request->url(), '/dnszone/1001/records/5001'));
});

test('wraps http errors in a BunnyApiException with a safe user message', function (): void {
    Http::fake(['api.bunny.net/*' => Http::response(status: 401)]);

    try {
        BunnyClient::for($this->account)->zones();
        $this->fail('Expected BunnyApiException');
    } catch (BunnyApiException $exception) {
        expect($exception->status)->toBe(401)
            ->and($exception->userMessage())->toContain($this->account->name)
            ->and($exception->userMessage())->not->toContain('secret-key');
    }
});

test('wraps connection failures in a BunnyApiException', function (): void {
    Http::fake(['api.bunny.net/*' => Http::failedConnection()]);

    BunnyClient::for($this->account)->zones();
})->throws(BunnyApiException::class);

test('lists pull zones with their custom hostnames only', function (): void {
    Http::fake([
        'api.bunny.net/pullzone?page=1*' => Http::response(bunnyPageFixture([bunnyPullZoneFixture()], hasMore: true)),
        'api.bunny.net/pullzone?page=2*' => Http::response(bunnyPageFixture([bunnyPullZoneFixture(['Id' => 7002, 'Name' => 'second', 'Hostnames' => []])])),
    ]);

    $pullZones = BunnyClient::for($this->account)->pullZones();

    expect($pullZones)->toHaveCount(2)
        ->and($pullZones[0]->hostnames)->toBe(['app.example.com'])
        ->and($pullZones[1]->hostnames)->toBe([]);
});

test('follows nextPage when listing shield zones', function (): void {
    Http::fake([
        'api.bunny.net/shield/shield-zones?page=1*' => Http::response(bunnyShieldPageFixture([bunnyShieldZoneFixture(['shieldZoneId' => 1])], currentPage: 1, nextPage: 2)),
        'api.bunny.net/shield/shield-zones?page=2*' => Http::response(bunnyShieldPageFixture([bunnyShieldZoneFixture(['shieldZoneId' => 2])], currentPage: 2)),
    ]);

    $zones = BunnyClient::for($this->account)->shieldZones();

    expect($zones->pluck('id')->all())->toBe([1, 2])
        ->and($zones[0]->pullZoneId)->toBe(7001);
});

test('searches shield event logs and maps the trigger match', function (): void {
    Http::fake([
        'api.bunny.net/shield/event-logs/3001/search' => Http::response(bunnyShieldSearchFixture([
            bunnyShieldEventFixture(log: ['UrlEncodedBody' => 'name=Bob&message=hi']),
        ], page: 0, totalPages: 3, total: 250)),
    ]);

    $result = BunnyClient::for($this->account)->searchShieldEvents(3001, 1000, 2000, '942550', page: 0, pageSize: 100);

    expect($result->totalPages)->toBe(3)
        ->and($result->hasMorePages())->toBeTrue()
        ->and($result->events)->toHaveCount(1)
        ->and($result->events[0]->ruleId)->toBe('942550')
        ->and($result->events[0]->triggerMatchKey)->toBe('RX')
        ->and($result->events[0]->triggerMatchValue)->toContain('message:hello world')
        ->and($result->events[0]->urlEncodedBody)->toBe('name=Bob&message=hi')
        ->and($result->events[0]->headers[0])->toBe(['name' => 'Host', 'value' => 'app.example.com']);

    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
        && $request->data() === ['from' => 1000, 'to' => 2000, 'query' => '942550', 'page' => 0, 'pageSize' => 100]);
});

test('aggregates shield events per rule via groupBy', function (): void {
    Http::fake(fn (Request $request) => Http::response(match ($request->data()['page']) {
        0 => bunnyShieldGroupsFixture([bunnyShieldRuleGroupFixture('932130', 10), bunnyShieldRuleGroupFixture('942550', 77, blocked: 3)], page: 0, totalPages: 2),
        default => bunnyShieldGroupsFixture([bunnyShieldRuleGroupFixture('930130', 958)], page: 1, totalPages: 2),
    }));

    $groups = BunnyClient::for($this->account)->shieldRuleGroups(3001, 1000, 2000);

    expect($groups->pluck('ruleId')->all())->toBe(['930130', '942550', '932130'])
        ->and($groups[1]->blocked)->toBe(3)
        ->and($groups[1]->logged)->toBe(74);

    Http::assertSent(fn (Request $request): bool => $request->data()['groupBy'] === ['ruleId']
        && $request->data()['from'] === 1000
        && str_ends_with($request->url(), '/shield/event-logs/3001/search'));
});
