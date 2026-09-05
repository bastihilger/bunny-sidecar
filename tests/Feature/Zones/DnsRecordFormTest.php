<?php

declare(strict_types=1);

use BunnySidecar\BunnySidecar\Enums\DnsEntryType;
use BunnySidecar\BunnySidecar\Enums\DnsRecordScope;
use BunnySidecar\BunnySidecar\Livewire\Dns\RecordForm;
use BunnySidecar\BunnySidecar\Models\BunnyAccount;
use BunnySidecar\BunnySidecar\Models\DnsRecordManager;
use BunnySidecar\BunnySidecar\Models\DnsRecordOwnership;
use BunnySidecar\BunnySidecar\Models\DnsZoneGrant;
use BunnySidecar\BunnySidecar\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function (): void {
    Http::preventStrayRequests();

    $this->account = BunnyAccount::factory()->create();
});

function recordFormTest(BunnyAccount $account): Testable
{
    return Livewire::test(RecordForm::class, [
        'account' => $account,
        'domain' => 'example.com',
        'zoneId' => 1001,
    ]);
}

test('creates a record with the validated payload', function (): void {
    Http::fake(['api.bunny.net/dnszone/1001/records' => Http::response(bunnyRecordFixture())]);

    $this->actingAsPanelUser(User::factory()->admin()->create());

    recordFormTest($this->account)
        ->call('open')
        ->set('form.type', DnsEntryType::A->value)
        ->set('form.name', 'www')
        ->set('form.value', '192.0.2.10')
        ->set('form.ttl', 300)
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('record-saved');

    Http::assertSent(fn (Request $request): bool => $request->method() === 'PUT'
        && str_ends_with($request->url(), '/dnszone/1001/records')
        && $request->data()['Type'] === DnsEntryType::A->value
        && $request->data()['Value'] === '192.0.2.10');
});

test('updating sends the full field set including untouched advanced settings', function (): void {
    Http::fake([
        'api.bunny.net/dnszone/1001' => Http::response(bunnyZoneFixture([
            'Records' => [bunnyRecordFixture([
                'Id' => 5001,
                'Type' => DnsEntryType::A->value,
                'Value' => '192.0.2.10',
                'MonitorType' => 2,
                'SmartRoutingType' => 1,
                'GeolocationLatitude' => 48.1,
                'GeolocationLongitude' => 11.5,
                'LatencyZone' => 'DE',
            ])],
        ])),
        'api.bunny.net/dnszone/1001/records/5001' => Http::response(status: 204),
    ]);

    $this->actingAsPanelUser(User::factory()->admin()->create());

    recordFormTest($this->account)
        ->call('open', recordId: 5001)
        ->assertSet('form.value', '192.0.2.10')
        ->set('form.value', '192.0.2.20')
        ->call('save')
        ->assertHasNoErrors();

    Http::assertSent(function (Request $request): bool {
        if ($request->method() !== 'POST' || ! str_ends_with($request->url(), '/dnszone/1001/records/5001')) {
            return false;
        }

        $data = $request->data();

        return $data['Value'] === '192.0.2.20'
            && $data['MonitorType'] === 2
            && $data['SmartRoutingType'] === 1
            && $data['GeolocationLatitude'] === 48.1
            && $data['LatencyZone'] === 'DE';
    });
});

test('rejects an invalid value for the record type', function (): void {
    Http::fake();

    $this->actingAsPanelUser(User::factory()->admin()->create());

    recordFormTest($this->account)
        ->call('open')
        ->set('form.type', DnsEntryType::A->value)
        ->set('form.value', 'not-an-ip')
        ->call('save')
        ->assertHasErrors(['form.value']);

    Http::assertNothingSent();
});

test('members cannot save a record type outside their add grant', function (): void {
    Http::fake();

    $member = User::factory()->member()->create();
    DnsZoneGrant::factory()->for($member)->for($this->account)
        ->allow(DnsEntryType::TXT)
        ->create(['zone_domain' => 'example.com']);

    $this->actingAsPanelUser($member);

    recordFormTest($this->account)
        ->call('open')
        ->set('form.type', DnsEntryType::A->value)
        ->set('form.value', '192.0.2.10')
        ->call('save')
        ->assertForbidden();

    Http::assertNothingSent();
});

test('creating a record stores the current user as its owner', function (): void {
    Http::fake(['api.bunny.net/dnszone/1001/records' => Http::response(bunnyRecordFixture(['Id' => 6001]))]);

    $member = User::factory()->member()->create();
    DnsZoneGrant::factory()->for($member)->for($this->account)
        ->allow(DnsEntryType::A)
        ->create(['zone_domain' => 'example.com']);

    $this->actingAsPanelUser($member);

    recordFormTest($this->account)
        ->call('open')
        ->set('form.type', DnsEntryType::A->value)
        ->set('form.name', 'www')
        ->set('form.value', '192.0.2.10')
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas(DnsRecordOwnership::class, [
        'bunny_account_id' => $this->account->id,
        'zone_domain' => 'example.com',
        'record_id' => 6001,
        'created_by' => $member->id,
    ]);
});

test('members with only an add grant can edit the records they created', function (): void {
    Http::fake([
        'api.bunny.net/dnszone/1001' => Http::response(bunnyZoneFixture([
            'Records' => [bunnyRecordFixture(['Id' => 5001, 'Type' => DnsEntryType::A->value])],
        ])),
        'api.bunny.net/dnszone/1001/records/5001' => Http::response(status: 204),
    ]);

    $member = User::factory()->member()->create();
    DnsZoneGrant::factory()->for($member)->for($this->account)
        ->allow(DnsEntryType::A)
        ->create(['zone_domain' => 'example.com']);
    DnsRecordOwnership::factory()->for($this->account)->for($member, 'creator')
        ->create(['zone_domain' => 'example.com', 'record_id' => 5001]);

    $this->actingAsPanelUser($member);

    recordFormTest($this->account)
        ->call('open', recordId: 5001)
        ->set('form.value', '192.0.2.20')
        ->call('save')
        ->assertHasNoErrors();

    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
        && str_ends_with($request->url(), '/dnszone/1001/records/5001'));
});

test('a subdomain-only add grant rejects records at the zone root with a name error', function (): void {
    Http::fake();

    $member = User::factory()->member()->create();
    DnsZoneGrant::factory()->for($member)->for($this->account)
        ->allowIn(DnsRecordScope::Subdomain, DnsEntryType::A)
        ->create(['zone_domain' => 'example.com']);

    $this->actingAsPanelUser($member);

    recordFormTest($this->account)
        ->call('open')
        ->set('form.type', DnsEntryType::A->value)
        ->set('form.name', '@')
        ->set('form.value', '192.0.2.10')
        ->call('save')
        ->assertHasErrors(['form.name']);

    Http::assertNothingSent();
});

test('moving an own subdomain record to the zone root requires a root add grant', function (): void {
    Http::fake([
        'api.bunny.net/dnszone/1001' => Http::response(bunnyZoneFixture([
            'Records' => [bunnyRecordFixture(['Id' => 5001, 'Type' => DnsEntryType::A->value, 'Name' => 'www'])],
        ])),
    ]);

    $member = User::factory()->member()->create();
    DnsZoneGrant::factory()->for($member)->for($this->account)
        ->allowIn(DnsRecordScope::Subdomain, DnsEntryType::A)
        ->create(['zone_domain' => 'example.com']);
    DnsRecordOwnership::factory()->for($this->account)->for($member, 'creator')
        ->create(['zone_domain' => 'example.com', 'record_id' => 5001]);

    $this->actingAsPanelUser($member);

    recordFormTest($this->account)
        ->call('open', recordId: 5001)
        ->set('form.name', '')
        ->call('save')
        ->assertHasErrors(['form.name']);

    Http::assertNotSent(fn (Request $request): bool => $request->method() === 'POST');
});

test('members assigned as manager can edit a record created by others', function (): void {
    Http::fake([
        'api.bunny.net/dnszone/1001' => Http::response(bunnyZoneFixture([
            'Records' => [bunnyRecordFixture(['Id' => 5001, 'Type' => DnsEntryType::A->value])],
        ])),
        'api.bunny.net/dnszone/1001/records/5001' => Http::response(status: 204),
    ]);

    $member = User::factory()->member()->create();
    DnsZoneGrant::factory()->for($member)->for($this->account)->create(['zone_domain' => 'example.com']);
    DnsRecordManager::factory()->for($this->account)->for($member)->create(['zone_domain' => 'example.com', 'record_id' => 5001]);

    $this->actingAsPanelUser($member);

    recordFormTest($this->account)
        ->call('open', recordId: 5001)
        ->set('form.value', '192.0.2.20')
        ->call('save')
        ->assertHasNoErrors();

    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
        && str_ends_with($request->url(), '/dnszone/1001/records/5001'));
});

test('members cannot open a record created by others unless assigned as manager', function (): void {
    Http::fake(['api.bunny.net/dnszone/1001' => Http::response(bunnyZoneFixture([
        'Records' => [bunnyRecordFixture(['Id' => 5001])],
    ]))]);

    $member = User::factory()->member()->create();
    DnsZoneGrant::factory()->for($member)->for($this->account)
        ->allow(DnsEntryType::A)
        ->create(['zone_domain' => 'example.com']);

    $this->actingAsPanelUser($member);

    recordFormTest($this->account)
        ->call('open', recordId: 5001)
        ->assertForbidden();
});

test('members without any add grant cannot open the create form', function (): void {
    Http::fake();

    $member = User::factory()->member()->create();
    DnsZoneGrant::factory()->for($member)->for($this->account)->create(['zone_domain' => 'example.com']);

    $this->actingAsPanelUser($member);

    recordFormTest($this->account)
        ->call('open')
        ->assertForbidden();
});
