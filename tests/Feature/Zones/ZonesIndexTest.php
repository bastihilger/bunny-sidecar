<?php

declare(strict_types=1);

use BunnySidecar\BunnySidecar\Livewire\Zones\Index;
use BunnySidecar\BunnySidecar\Models\BunnyAccount;
use BunnySidecar\BunnySidecar\Models\DnsZoneGrant;
use BunnySidecar\BunnySidecar\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function (): void {
    Http::preventStrayRequests();
});

test('guests are redirected to the panel login page', function (): void {
    $this->get(route('bunny-sidecar.zones.index'))->assertRedirect(route('bunny-sidecar.login'));
});

test('admins see the zones of every bunny account', function (): void {
    BunnyAccount::factory()->create(['name' => 'Alpha', 'api_key' => 'alpha-key']);
    BunnyAccount::factory()->create(['name' => 'Beta', 'api_key' => 'beta-key']);

    Http::fake(fn (Request $request) => $request->header('AccessKey') === ['alpha-key']
        ? Http::response(bunnyPageFixture([bunnyZoneFixture(['Id' => 1, 'Domain' => 'alpha.com'])]))
        : Http::response(bunnyPageFixture([bunnyZoneFixture(['Id' => 2, 'Domain' => 'beta.com'])])));

    $this->actingAsPanelUser(User::factory()->admin()->create());

    $this->get(route('bunny-sidecar.zones.index'))
        ->assertOk()
        ->assertSee('alpha.com')
        ->assertSee('beta.com')
        ->assertSee(__('Bunny Accounts'));
});

test('members only see zones granted to them and no admin navigation', function (): void {
    $member = User::factory()->member()->create();
    $account = BunnyAccount::factory()->create();
    DnsZoneGrant::factory()->for($member)->for($account)->create(['zone_domain' => 'granted.com']);

    Http::fake(['api.bunny.net/dnszone*' => Http::response(bunnyPageFixture([
        bunnyZoneFixture(['Id' => 1, 'Domain' => 'granted.com']),
        bunnyZoneFixture(['Id' => 2, 'Domain' => 'hidden.com']),
    ]))]);

    $this->actingAsPanelUser($member);

    $this->get(route('bunny-sidecar.zones.index'))
        ->assertOk()
        ->assertSee('granted.com')
        ->assertDontSee('hidden.com')
        ->assertDontSee(__('Bunny Accounts'));
});

test('filters zones by the search term', function (): void {
    BunnyAccount::factory()->create();
    Http::fake(['api.bunny.net/dnszone*' => Http::response(bunnyPageFixture([
        bunnyZoneFixture(['Id' => 1, 'Domain' => 'first.com']),
        bunnyZoneFixture(['Id' => 2, 'Domain' => 'second.com']),
    ]))]);

    $this->actingAsPanelUser(User::factory()->admin()->create());

    Livewire::test(Index::class)
        ->set('search', 'first')
        ->assertSee('first.com')
        ->assertDontSee('second.com');
});

test('refreshing reloads the zones from bunny.net', function (): void {
    BunnyAccount::factory()->create();
    Http::fake(['api.bunny.net/dnszone*' => Http::response(bunnyPageFixture([bunnyZoneFixture()]))]);

    $this->actingAsPanelUser(User::factory()->admin()->create());

    Livewire::test(Index::class)
        ->call('refreshZones');

    Http::assertSentCount(2);
});

test('renders an error callout when the bunny api is unreachable', function (): void {
    BunnyAccount::factory()->create(['name' => 'Alpha']);
    Http::fake(['api.bunny.net/*' => Http::response(status: 500)]);

    $this->actingAsPanelUser(User::factory()->admin()->create());

    $this->get(route('bunny-sidecar.zones.index'))
        ->assertOk()
        ->assertSee('Alpha');
});
