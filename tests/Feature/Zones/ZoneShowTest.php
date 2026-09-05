<?php

declare(strict_types=1);

use BunnySidecar\BunnySidecar\Enums\DnsEntryType;
use BunnySidecar\BunnySidecar\Enums\DnsRecordScope;
use BunnySidecar\BunnySidecar\Livewire\Zones\Show;
use BunnySidecar\BunnySidecar\Models\BunnyAccount;
use BunnySidecar\BunnySidecar\Models\DnsRecordManager;
use BunnySidecar\BunnySidecar\Models\DnsRecordOwnership;
use BunnySidecar\BunnySidecar\Models\DnsZoneGrant;
use BunnySidecar\BunnySidecar\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function (): void {
    Http::preventStrayRequests();

    $this->account = BunnyAccount::factory()->create();
});

/**
 * @param  list<array<string, mixed>>  $records
 */
function fakeZoneWithRecords(array $records = []): void
{
    Http::fake([
        'api.bunny.net/dnszone?*' => Http::response(bunnyPageFixture([bunnyZoneFixture()])),
        'api.bunny.net/dnszone/1001' => Http::response(bunnyZoneFixture(['Records' => $records])),
        'api.bunny.net/dnszone/1001/records/*' => Http::response(status: 204),
    ]);
}

test('members without a grant get a 403', function (): void {
    fakeZoneWithRecords();

    $this->actingAsPanelUser(User::factory()->member()->create());

    $this->get(route('bunny-sidecar.zones.show', ['account' => $this->account, 'domain' => 'example.com']))
        ->assertForbidden();
});

test('renders a 404 for a domain that does not exist in the account', function (): void {
    fakeZoneWithRecords();

    $this->actingAsPanelUser(User::factory()->admin()->create());

    $this->get(route('bunny-sidecar.zones.show', ['account' => $this->account, 'domain' => 'missing.com']))
        ->assertNotFound();
});

test('granted members see all records read-only without write actions', function (): void {
    fakeZoneWithRecords([
        bunnyRecordFixture(['Id' => 1, 'Type' => DnsEntryType::A->value, 'Value' => '192.0.2.10']),
        bunnyRecordFixture(['Id' => 2, 'Type' => DnsEntryType::TXT->value, 'Name' => '_dmarc', 'Value' => 'v=DMARC1']),
    ]);

    $member = User::factory()->member()->create();
    DnsZoneGrant::factory()->for($member)->for($this->account)->create(['zone_domain' => 'example.com']);

    $this->actingAsPanelUser($member);

    $this->get(route('bunny-sidecar.zones.show', ['account' => $this->account, 'domain' => 'example.com']))
        ->assertOk()
        ->assertSee('192.0.2.10')
        ->assertSee('_dmarc')
        ->assertDontSee(__('Add record'));
});

test('records are sorted alphabetically by type, then by name', function (): void {
    fakeZoneWithRecords([
        bunnyRecordFixture(['Id' => 1, 'Type' => DnsEntryType::TXT->value, 'Name' => 'spf', 'Value' => 'value-txt']),
        bunnyRecordFixture(['Id' => 2, 'Type' => DnsEntryType::A->value, 'Name' => 'zzz', 'Value' => 'value-a-zzz']),
        bunnyRecordFixture(['Id' => 3, 'Type' => DnsEntryType::MX->value, 'Name' => 'mail', 'Value' => 'value-mx']),
        bunnyRecordFixture(['Id' => 4, 'Type' => DnsEntryType::A->value, 'Name' => 'aaa', 'Value' => 'value-a-aaa']),
    ]);

    $this->actingAsPanelUser(User::factory()->admin()->create());

    $this->get(route('bunny-sidecar.zones.show', ['account' => $this->account, 'domain' => 'example.com']))
        ->assertOk()
        ->assertSeeInOrder(['value-a-aaa', 'value-a-zzz', 'value-mx', 'value-txt']);
});

test('admins see the add record button and the zone as page title', function (): void {
    fakeZoneWithRecords([bunnyRecordFixture()]);

    $this->actingAsPanelUser(User::factory()->admin()->create());

    $this->get(route('bunny-sidecar.zones.show', ['account' => $this->account, 'domain' => 'example.com']))
        ->assertOk()
        ->assertSee(__('Add record'))
        ->assertSee('<title>example.com - ', escape: false);
});

test('members cannot delete records created by others unless assigned as manager', function (): void {
    fakeZoneWithRecords([bunnyRecordFixture(['Id' => 5001])]);

    $member = User::factory()->member()->create();
    DnsZoneGrant::factory()->for($member)->for($this->account)
        ->allow(DnsEntryType::A)
        ->create(['zone_domain' => 'example.com']);

    $this->actingAsPanelUser($member);

    Livewire::test(Show::class, ['account' => $this->account, 'domain' => 'example.com'])
        ->call('deleteRecord')
        ->assertStatus(404);

    Livewire::test(Show::class, ['account' => $this->account, 'domain' => 'example.com'])
        ->call('confirmDelete', 5001)
        ->assertForbidden();
});

test('members assigned as manager can delete the record, which drops the assignment', function (): void {
    fakeZoneWithRecords([bunnyRecordFixture(['Id' => 5001, 'Type' => DnsEntryType::A->value])]);

    $member = User::factory()->member()->create();
    DnsZoneGrant::factory()->for($member)->for($this->account)->create(['zone_domain' => 'example.com']);
    DnsRecordManager::factory()->for($this->account)->for($member)->create(['zone_domain' => 'example.com', 'record_id' => 5001]);

    $this->actingAsPanelUser($member);

    Livewire::test(Show::class, ['account' => $this->account, 'domain' => 'example.com'])
        ->call('confirmDelete', 5001)
        ->call('deleteRecord')
        ->assertHasNoErrors();

    Http::assertSent(fn (Request $request): bool => $request->method() === 'DELETE'
        && str_ends_with($request->url(), '/dnszone/1001/records/5001'));

    $this->assertDatabaseMissing(DnsRecordManager::class, ['record_id' => 5001]);
});

test('members with an add grant can delete their own records, which drops the ownership', function (): void {
    fakeZoneWithRecords([bunnyRecordFixture(['Id' => 5001, 'Type' => DnsEntryType::A->value])]);

    $member = User::factory()->member()->create();
    DnsZoneGrant::factory()->for($member)->for($this->account)
        ->allow(DnsEntryType::A)
        ->create(['zone_domain' => 'example.com']);
    DnsRecordOwnership::factory()->for($this->account)->for($member, 'creator')
        ->create(['zone_domain' => 'example.com', 'record_id' => 5001]);

    $this->actingAsPanelUser($member);

    Livewire::test(Show::class, ['account' => $this->account, 'domain' => 'example.com'])
        ->assertSee(__('Delete'))
        ->call('confirmDelete', 5001)
        ->call('deleteRecord')
        ->assertHasNoErrors();

    Http::assertSent(fn (Request $request): bool => $request->method() === 'DELETE'
        && str_ends_with($request->url(), '/dnszone/1001/records/5001'));

    $this->assertDatabaseMissing(DnsRecordOwnership::class, ['record_id' => 5001]);
});

test('a manager assignment covers only that record', function (): void {
    fakeZoneWithRecords([
        bunnyRecordFixture(['Id' => 5001, 'Type' => DnsEntryType::A->value, 'Name' => 'www']),
        bunnyRecordFixture(['Id' => 5002, 'Type' => DnsEntryType::A->value, 'Name' => 'mail']),
    ]);

    $member = User::factory()->member()->create();
    DnsZoneGrant::factory()->for($member)->for($this->account)->create(['zone_domain' => 'example.com']);
    DnsRecordManager::factory()->for($this->account)->for($member)->create(['zone_domain' => 'example.com', 'record_id' => 5002]);

    $this->actingAsPanelUser($member);

    Livewire::test(Show::class, ['account' => $this->account, 'domain' => 'example.com'])
        ->call('confirmDelete', 5001)
        ->assertForbidden();
});

test('a root-only add grant does not cover an own subdomain record', function (): void {
    fakeZoneWithRecords([bunnyRecordFixture(['Id' => 5001, 'Type' => DnsEntryType::A->value, 'Name' => 'www'])]);

    $member = User::factory()->member()->create();
    DnsZoneGrant::factory()->for($member)->for($this->account)
        ->allowIn(DnsRecordScope::Root, DnsEntryType::A)
        ->create(['zone_domain' => 'example.com']);
    DnsRecordOwnership::factory()->for($this->account)->for($member, 'creator')
        ->create(['zone_domain' => 'example.com', 'record_id' => 5001]);

    $this->actingAsPanelUser($member);

    Livewire::test(Show::class, ['account' => $this->account, 'domain' => 'example.com'])
        ->assertDontSee(__('Record actions'))
        ->call('confirmDelete', 5001)
        ->assertForbidden();
});

test('admins assign record managers from members with access to the zone', function (): void {
    fakeZoneWithRecords([bunnyRecordFixture(['Id' => 5001])]);

    $granted = User::factory()->member()->create(['name' => 'Granted Member']);
    DnsZoneGrant::factory()->for($granted)->for($this->account)->create(['zone_domain' => 'example.com']);
    $stranger = User::factory()->member()->create(['name' => 'Stranger']);

    $this->actingAsPanelUser(User::factory()->admin()->create());

    Livewire::test(Show::class, ['account' => $this->account, 'domain' => 'example.com'])
        ->assertSee(__('Managers'))
        ->assertSee('Granted Member')
        ->assertDontSee('Stranger')
        ->set('managerIds.5001', [(string) $granted->id, (string) $stranger->id])
        ->assertHasNoErrors()
        ->assertSet('managerIds.5001', [$granted->id]);

    $this->assertDatabaseHas(DnsRecordManager::class, ['record_id' => 5001, 'user_id' => $granted->id, 'zone_domain' => 'example.com'])
        ->assertDatabaseMissing(DnsRecordManager::class, ['record_id' => 5001, 'user_id' => $stranger->id]);
});

test('the managers column is hidden when no member has access to the zone', function (): void {
    fakeZoneWithRecords([bunnyRecordFixture(['Id' => 5001])]);

    $this->actingAsPanelUser(User::factory()->admin()->create());

    Livewire::test(Show::class, ['account' => $this->account, 'domain' => 'example.com'])
        ->assertDontSee(__('Managers'));
});

test('members cannot assign record managers', function (): void {
    fakeZoneWithRecords([bunnyRecordFixture(['Id' => 5001])]);

    $member = User::factory()->member()->create();
    DnsZoneGrant::factory()->for($member)->for($this->account)->allowAll()->create(['zone_domain' => 'example.com']);

    $this->actingAsPanelUser($member);

    Livewire::test(Show::class, ['account' => $this->account, 'domain' => 'example.com'])
        ->assertDontSee(__('Managers'))
        ->set('managerIds.5001', [(string) $member->id])
        ->assertForbidden();

    $this->assertDatabaseCount(DnsRecordManager::class, 0);
});

test('delete is refused when the grant only covers other record types', function (): void {
    fakeZoneWithRecords([bunnyRecordFixture(['Id' => 5001, 'Type' => DnsEntryType::TXT->value, 'Name' => 'x', 'Value' => 'y'])]);

    $member = User::factory()->member()->create();
    DnsZoneGrant::factory()->for($member)->for($this->account)
        ->allow(DnsEntryType::A)
        ->create(['zone_domain' => 'example.com']);
    DnsRecordOwnership::factory()->for($this->account)->for($member, 'creator')
        ->create(['zone_domain' => 'example.com', 'record_id' => 5001]);

    $this->actingAsPanelUser($member);

    Livewire::test(Show::class, ['account' => $this->account, 'domain' => 'example.com'])
        ->call('confirmDelete', 5001)
        ->assertForbidden();
});
