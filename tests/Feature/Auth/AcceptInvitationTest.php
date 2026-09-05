<?php

declare(strict_types=1);

use BunnySidecar\BunnySidecar\Enums\DnsEntryType;
use BunnySidecar\BunnySidecar\Enums\DnsRecordScope;
use BunnySidecar\BunnySidecar\Enums\UserRole;
use BunnySidecar\BunnySidecar\Livewire\Auth\AcceptInvitation;
use BunnySidecar\BunnySidecar\Models\BunnyAccount;
use BunnySidecar\BunnySidecar\Models\Invitation;
use BunnySidecar\BunnySidecar\Models\User;
use Illuminate\Support\Str;
use Livewire\Livewire;

/**
 * @param  array<string, mixed>  $attributes
 * @return array{0: Invitation, 1: string}
 */
function issuedInvitation(array $attributes = []): array
{
    $plainToken = Str::random(64);

    $invitation = Invitation::factory()->create([
        'token' => hash('sha256', $plainToken),
        ...$attributes,
    ]);

    return [$invitation, $plainToken];
}

test('an invited member can set up their account and is logged in with their grants', function (): void {
    $account = BunnyAccount::factory()->create();

    [$invitation, $plainToken] = issuedInvitation([
        'email' => 'member@example.com',
        'name' => 'Invited Member',
        'zone_grants' => [[
            'bunny_account_id' => $account->id,
            'zone_domain' => 'example.com',
            'permissions' => [DnsRecordScope::Subdomain->value => [DnsEntryType::A->value]],
        ]],
    ]);

    $this->get(route('bunny-sidecar.invitations.accept', $plainToken))
        ->assertOk()
        ->assertSee('member@example.com');

    Livewire::test(AcceptInvitation::class, ['token' => $plainToken])
        ->assertSet('name', 'Invited Member')
        ->set('password', 'new-password')
        ->set('password_confirmation', 'new-password')
        ->call('accept')
        ->assertRedirect(route('bunny-sidecar.zones.index'));

    $user = User::query()->where('email', 'member@example.com')->firstOrFail();

    expect($user->role)->toBe(UserRole::Member)
        ->and($user->zoneGrants)->toHaveCount(1)
        ->and($user->zoneGrants->first()?->zone_domain)->toBe('example.com')
        ->and($user->zoneGrants->first()?->permissions->allows(DnsRecordScope::Subdomain, DnsEntryType::A))->toBeTrue()
        ->and($invitation->refresh()->accepted_at)->not->toBeNull();

    $this->assertAuthenticatedAs($user, 'bunny-sidecar');
});

test('an invited admin gets the admin role and no grants', function (): void {
    [$invitation, $plainToken] = issuedInvitation([
        'role' => UserRole::Admin,
        'zone_grants' => null,
    ]);

    Livewire::test(AcceptInvitation::class, ['token' => $plainToken])
        ->set('name', 'New Admin')
        ->set('password', 'new-password')
        ->set('password_confirmation', 'new-password')
        ->call('accept')
        ->assertRedirect(route('bunny-sidecar.zones.index'));

    $user = User::query()->where('email', $invitation->email)->firstOrFail();

    expect($user->isAdmin())->toBeTrue()
        ->and($user->zoneGrants)->toHaveCount(0);
});

test('grants for deleted bunny accounts are skipped on acceptance', function (): void {
    $account = BunnyAccount::factory()->create();

    [, $plainToken] = issuedInvitation([
        'zone_grants' => [[
            'bunny_account_id' => $account->id,
            'zone_domain' => 'example.com',
            'permissions' => [],
        ]],
    ]);

    $account->delete();

    Livewire::test(AcceptInvitation::class, ['token' => $plainToken])
        ->set('name', 'Member')
        ->set('password', 'new-password')
        ->set('password_confirmation', 'new-password')
        ->call('accept')
        ->assertRedirect(route('bunny-sidecar.zones.index'));

    expect(User::query()->latest('id')->firstOrFail()->zoneGrants)->toHaveCount(0);
});

test('expired, used, and unknown tokens render the invalid state', function (): void {
    [, $expiredToken] = issuedInvitation(['expires_at' => now()->subDay()]);
    [, $usedToken] = issuedInvitation(['accepted_at' => now()]);

    foreach ([$expiredToken, $usedToken, 'unknown-token'] as $token) {
        $this->get(route('bunny-sidecar.invitations.accept', $token))
            ->assertOk()
            ->assertSee(__('Invitation invalid'));
    }
});

test('accepting with an invalid token is refused', function (): void {
    [, $plainToken] = issuedInvitation(['expires_at' => now()->subDay()]);

    Livewire::test(AcceptInvitation::class, ['token' => $plainToken])
        ->set('name', 'Member')
        ->set('password', 'new-password')
        ->set('password_confirmation', 'new-password')
        ->call('accept')
        ->assertForbidden();

    $this->assertGuest('bunny-sidecar');
});

test('rejects a mismatched password confirmation', function (): void {
    [, $plainToken] = issuedInvitation();

    Livewire::test(AcceptInvitation::class, ['token' => $plainToken])
        ->set('name', 'Member')
        ->set('password', 'new-password')
        ->set('password_confirmation', 'different')
        ->call('accept')
        ->assertHasErrors(['password']);

    $this->assertGuest('bunny-sidecar');
});

test('authenticated users are redirected away from the invitation page', function (): void {
    [, $plainToken] = issuedInvitation();

    $this->actingAsPanelUser(User::factory()->create());

    $this->get(route('bunny-sidecar.invitations.accept', $plainToken))
        ->assertRedirect(route('bunny-sidecar.zones.index'));
});
