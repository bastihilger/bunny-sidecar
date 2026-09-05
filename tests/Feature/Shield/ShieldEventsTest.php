<?php

declare(strict_types=1);

use BunnySidecar\BunnySidecar\Livewire\Shield\Events;
use BunnySidecar\BunnySidecar\Models\BunnyAccount;
use BunnySidecar\BunnySidecar\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function (): void {
    Http::preventStrayRequests();
});

/**
 * Fakes one shield zone whose event search returns the given rows (and a
 * grouped response listing rules 942550 and 932130 when groupBy is sent).
 *
 * @param  list<array<string, mixed>>|null  $rows
 * @param  array<string, mixed>  $searchResponse
 */
function fakeShieldZones(?array $rows = null, array $searchResponse = []): void
{
    $rows ??= [bunnyShieldEventFixture(log: ['TriggerMatch' => ['Key' => 'ARGS', 'Value' => 'the exact matched fragment']])];

    Http::fake([
        'api.bunny.net/pullzone*' => Http::response(bunnyPageFixture([bunnyPullZoneFixture()])),
        'api.bunny.net/shield/shield-zones*' => Http::response(bunnyShieldPageFixture([bunnyShieldZoneFixture()])),
        'api.bunny.net/shield/event-logs/*' => fn (Request $request) => isset($request->data()['groupBy'])
            ? Http::response(bunnyShieldGroupsFixture([bunnyShieldRuleGroupFixture('942550', 77), bunnyShieldRuleGroupFixture('12345', 500), bunnyShieldRuleGroupFixture('932130', 10, blocked: 2)]))
            : Http::response(...($searchResponse ?: ['body' => bunnyShieldSearchFixture($rows, totalPages: $rows === [] ? 0 : 1)])),
    ]);
}

test('guests are redirected and members are forbidden', function (): void {
    $this->get(route('bunny-sidecar.shield.events'))->assertRedirect(route('bunny-sidecar.login'));

    $this->actingAsPanelUser(User::factory()->member()->create());

    $this->get(route('bunny-sidecar.shield.events'))->assertForbidden();
});

test('admins see a shield zone select labelled by hostname', function (): void {
    BunnyAccount::factory()->create();
    fakeShieldZones();

    $this->actingAsPanelUser(User::factory()->admin()->create());

    $this->get(route('bunny-sidecar.shield.events'))
        ->assertOk()
        ->assertSee('app.example.com')
        ->assertSee('Choose a shield zone first');
});

test('searching a rule lists its events including the trigger match', function (): void {
    $account = BunnyAccount::factory()->create();
    fakeShieldZones();

    $this->actingAsPanelUser(User::factory()->admin()->create());

    Livewire::test(Events::class)
        ->set('shieldZone', "{$account->id}:3001")
        ->set('ruleId', '942550')
        ->call('search')
        ->assertHasNoErrors()
        ->assertSee('the exact matched fragment')
        ->assertSee('ARGS')
        ->assertSee('198.51.100.*')
        ->assertSee('JSON-based SQL Injection')
        ->assertSee('1 event for rule 942550 on app.example.com');

    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/shield/event-logs/3001/search')
        && $request->data()['query'] === '942550'
        && $request->data()['to'] - $request->data()['from'] === config()->integer('bunny-sidecar.api.shield_events_hours') * 3600 * 1000);
});

test('validates the rule id and shield zone before searching', function (): void {
    BunnyAccount::factory()->create();
    fakeShieldZones();

    $this->actingAsPanelUser(User::factory()->admin()->create());

    Livewire::test(Events::class)
        ->set('ruleId', 'abc')
        ->call('search')
        ->assertHasErrors(['ruleId', 'shieldZone']);

    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/event-logs/'));
});

test('shows an empty state when the rule produced no events', function (): void {
    $account = BunnyAccount::factory()->create();
    fakeShieldZones(rows: []);

    $this->actingAsPanelUser(User::factory()->admin()->create());

    Livewire::test(Events::class)
        ->set('shieldZone', "{$account->id}:3001")
        ->set('ruleId', '942550')
        ->call('search')
        ->assertSee('No events for rule 942550.');
});

test('shows an error callout when the event search fails', function (): void {
    $account = BunnyAccount::factory()->create(['name' => 'Alpha']);
    fakeShieldZones(searchResponse: ['status' => 401]);

    $this->actingAsPanelUser(User::factory()->admin()->create());

    Livewire::test(Events::class)
        ->set('shieldZone', "{$account->id}:3001")
        ->set('ruleId', '942550')
        ->call('search')
        ->assertSee('rejected the API key of account "Alpha"');
});

test('remembers the last searched shield zone and rule id', function (): void {
    $account = BunnyAccount::factory()->create();
    fakeShieldZones();

    $this->actingAsPanelUser(User::factory()->admin()->create());

    Livewire::test(Events::class)
        ->set('shieldZone', "{$account->id}:3001")
        ->set('ruleId', '942550')
        ->call('search');

    Livewire::test(Events::class)
        ->assertSet('shieldZone', "{$account->id}:3001")
        ->assertSet('ruleId', '942550');
});

test('pretty-prints json matched data', function (): void {
    $account = BunnyAccount::factory()->create();
    fakeShieldZones([
        bunnyShieldEventFixture(log: ['TriggerMatch' => ['Key' => 'RX', 'Value' => '{"components":[{"snapshot":"{\"data\":{\"message\":\"needle\"}}"}]}']]),
    ]);

    $this->actingAsPanelUser(User::factory()->admin()->create());

    Livewire::test(Events::class)
        ->set('shieldZone', "{$account->id}:3001")
        ->set('ruleId', '942550')
        ->call('search')
        ->assertSeeHtml('&quot;message&quot;: &quot;needle&quot;');
});

test('lists only the WAF rules that fired on the selected shield zone, sorted by id', function (): void {
    $account = BunnyAccount::factory()->create();
    fakeShieldZones();

    $this->actingAsPanelUser(User::factory()->admin()->create());

    Livewire::test(Events::class)
        ->assertSee('Choose a shield zone first')
        ->set('shieldZone', "{$account->id}:3001")
        ->assertSee('932130 · 10 events, 2 blocked')
        ->assertSee('942550 · 77 events')
        ->assertSeeHtmlInOrder(['value="932130"', 'value="942550"'])
        ->assertDontSee('12345 · 500 events');
});

test('clears a remembered rule that did not fire on the newly selected zone', function (): void {
    $account = BunnyAccount::factory()->create();
    fakeShieldZones();
    session(['bunny-sidecar.shield-events.rule_id' => '999999']);

    $this->actingAsPanelUser(User::factory()->admin()->create());

    Livewire::test(Events::class)
        ->assertSet('ruleId', '999999')
        ->set('shieldZone', "{$account->id}:3001")
        ->assertSet('ruleId', '');
});
