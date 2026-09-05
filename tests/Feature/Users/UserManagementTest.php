<?php

declare(strict_types=1);

use BunnySidecar\BunnySidecar\Enums\DnsEntryType;
use BunnySidecar\BunnySidecar\Enums\DnsRecordScope;
use BunnySidecar\BunnySidecar\Enums\UserRole;
use BunnySidecar\BunnySidecar\Livewire\Forms\ZoneGrantsForm;
use BunnySidecar\BunnySidecar\Livewire\Users\Edit;
use BunnySidecar\BunnySidecar\Livewire\Users\Index;
use BunnySidecar\BunnySidecar\Models\BunnyAccount;
use BunnySidecar\BunnySidecar\Models\DnsZoneGrant;
use BunnySidecar\BunnySidecar\Models\User;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function (): void {
    Http::preventStrayRequests();
    Http::fake(['api.bunny.net/*' => Http::response(bunnyPageFixture([bunnyZoneFixture()]))]);

    $this->admin = User::factory()->admin()->create();
});

test('members cannot access the user administration', function (): void {
    $this->actingAsPanelUser(User::factory()->member()->create());

    $this->get(route('bunny-sidecar.users.index'))->assertForbidden();
    $this->get(route('bunny-sidecar.users.edit', $this->admin))->assertForbidden();
    Livewire::test(Index::class)->assertForbidden();
});

test('admins see users and pending invitations', function (): void {
    User::factory()->member()->create(['name' => 'Some Member']);

    $this->actingAsPanelUser($this->admin);

    $this->get(route('bunny-sidecar.users.index'))
        ->assertOk()
        ->assertSee('Some Member')
        ->assertSee(__('No pending invitations.'));
});

test('admins can replace the zone grants of a member', function (): void {
    $member = User::factory()->member()->create();
    $account = BunnyAccount::factory()->create();
    $oldGrant = DnsZoneGrant::factory()->for($member)->create();

    $this->actingAsPanelUser($this->admin);

    Livewire::test(Edit::class, ['user' => $member])
        ->call('addGrantRow')
        ->set('grantsForm.rows.1.zone', "{$account->id}:example.com")
        ->set('grantsForm.rows.1.subdomain_mode', ZoneGrantsForm::MODE_SELECTED)
        ->set('grantsForm.rows.1.subdomain_types', [DnsEntryType::CNAME->value])
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('bunny-sidecar.users.index'));

    $grants = $member->refresh()->zoneGrants;

    expect($grants)->toHaveCount(2)
        ->and($grants->pluck('zone_domain')->all())->toContain('example.com', $oldGrant->zone_domain)
        ->and($grants->firstWhere('zone_domain', 'example.com')?->permissions->allows(DnsRecordScope::Subdomain, DnsEntryType::CNAME))->toBeTrue()
        ->and($grants->firstWhere('zone_domain', 'example.com')?->permissions->allowsAny(DnsRecordScope::Root))->toBeFalse();
});

test('promoting a member to admin removes the need for grants', function (): void {
    $member = User::factory()->member()->create();

    $this->actingAsPanelUser($this->admin);

    Livewire::test(Edit::class, ['user' => $member])
        ->set('role', UserRole::Admin->value)
        ->call('save')
        ->assertHasNoErrors();

    expect($member->refresh()->isAdmin())->toBeTrue();
});

test('the last admin cannot be demoted', function (): void {
    $this->actingAsPanelUser($this->admin);

    Livewire::test(Edit::class, ['user' => $this->admin])
        ->set('role', UserRole::Member->value)
        ->call('save');

    expect($this->admin->refresh()->isAdmin())->toBeTrue();
});

test('admins cannot delete themselves or the last admin', function (): void {
    $this->actingAsPanelUser($this->admin);

    Livewire::test(Index::class)
        ->call('confirmDelete', $this->admin->id)
        ->assertForbidden();

    expect(User::query()->whereKey($this->admin->id)->exists())->toBeTrue();
});

test('admins can delete a member together with their grants', function (): void {
    $member = User::factory()->member()->create();
    $grant = DnsZoneGrant::factory()->for($member)->create();

    $this->actingAsPanelUser($this->admin);

    Livewire::test(Index::class)
        ->call('confirmDelete', $member->id)
        ->call('deleteUser')
        ->assertHasNoErrors();

    $this->assertDatabaseMissing('bunny_sidecar_users', ['id' => $member->id]);
    $this->assertDatabaseMissing('bunny_sidecar_zone_grants', ['id' => $grant->id]);
});
