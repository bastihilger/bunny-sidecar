<?php

declare(strict_types=1);

use BunnySidecar\BunnySidecar\Livewire\Accounts\Index;
use BunnySidecar\BunnySidecar\Models\BunnyAccount;
use BunnySidecar\BunnySidecar\Models\DnsZoneGrant;
use BunnySidecar\BunnySidecar\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function (): void {
    Http::preventStrayRequests();
});

test('members and guests cannot access the accounts page', function (): void {
    $this->get(route('bunny-sidecar.accounts.index'))->assertRedirect(route('bunny-sidecar.login'));

    $this->actingAsPanelUser(User::factory()->member()->create());
    $this->get(route('bunny-sidecar.accounts.index'))->assertForbidden();

    Livewire::test(Index::class)->assertForbidden();
});

test('admins can create an account and the key is stored encrypted', function (): void {
    $this->actingAsPanelUser(User::factory()->admin()->create());

    Livewire::test(Index::class)
        ->call('create')
        ->set('form.name', 'Main Account')
        ->set('form.api_key', 'super-secret-key')
        ->call('save')
        ->assertHasNoErrors();

    $account = BunnyAccount::query()->firstOrFail();
    $rawKey = DB::table('bunny_sidecar_accounts')->value('api_key');

    expect($account->name)->toBe('Main Account')
        ->and($account->api_key)->toBe('super-secret-key')
        ->and($rawKey)->not->toContain('super-secret-key');
});

test('requires an api key when creating but keeps it when editing with an empty key', function (): void {
    $this->actingAsPanelUser(User::factory()->admin()->create());

    Livewire::test(Index::class)
        ->call('create')
        ->set('form.name', 'Main Account')
        ->call('save')
        ->assertHasErrors(['form.api_key']);

    $account = BunnyAccount::factory()->create(['api_key' => 'original-key']);

    Livewire::test(Index::class)
        ->call('edit', $account->id)
        ->set('form.name', 'Renamed')
        ->call('save')
        ->assertHasNoErrors();

    expect($account->refresh()->name)->toBe('Renamed')
        ->and($account->api_key)->toBe('original-key');
});

test('renders only a masked key hint, never the key itself', function (): void {
    BunnyAccount::factory()->create(['api_key' => 'super-secret-key']);

    $this->actingAsPanelUser(User::factory()->admin()->create());

    $this->get(route('bunny-sidecar.accounts.index'))
        ->assertOk()
        ->assertSee('••••-key')
        ->assertDontSee('super-secret-key');
});

test('test connection reports the zone count', function (): void {
    Http::fake(['api.bunny.net/dnszone*' => Http::response(bunnyPageFixture([bunnyZoneFixture()]))]);
    $account = BunnyAccount::factory()->create(['name' => 'Main']);

    $this->actingAsPanelUser(User::factory()->admin()->create());

    Livewire::test(Index::class)
        ->call('testConnection', $account->id)
        ->assertHasNoErrors();

    Http::assertSentCount(1);
});

test('deleting an account removes it together with its grants', function (): void {
    $account = BunnyAccount::factory()->create();
    $grant = DnsZoneGrant::factory()->for($account)->create();

    $this->actingAsPanelUser(User::factory()->admin()->create());

    Livewire::test(Index::class)
        ->call('confirmDelete', $account->id)
        ->call('delete')
        ->assertHasNoErrors();

    $this->assertDatabaseMissing('bunny_sidecar_accounts', ['id' => $account->id]);
    $this->assertDatabaseMissing('bunny_sidecar_zone_grants', ['id' => $grant->id]);
});
