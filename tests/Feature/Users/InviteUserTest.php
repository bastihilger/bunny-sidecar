<?php

declare(strict_types=1);

use BunnySidecar\BunnySidecar\Enums\DnsEntryType;
use BunnySidecar\BunnySidecar\Enums\UserRole;
use BunnySidecar\BunnySidecar\Livewire\Forms\ZoneGrantsForm;
use BunnySidecar\BunnySidecar\Livewire\Users\Index;
use BunnySidecar\BunnySidecar\Livewire\Users\Invite;
use BunnySidecar\BunnySidecar\Mail\UserInvitationMail;
use BunnySidecar\BunnySidecar\Models\BunnyAccount;
use BunnySidecar\BunnySidecar\Models\Invitation;
use BunnySidecar\BunnySidecar\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

beforeEach(function (): void {
    Http::preventStrayRequests();
    Http::fake(['api.bunny.net/*' => Http::response(bunnyPageFixture([bunnyZoneFixture()]))]);
    Mail::fake();

    $this->admin = User::factory()->admin()->create();
});

test('members cannot access the invite page', function (): void {
    $this->actingAsPanelUser(User::factory()->member()->create());

    $this->get(route('bunny-sidecar.users.invite'))->assertForbidden();
    Livewire::test(Invite::class)->assertForbidden();
});

test('inviting a member stores the grant snapshot and sends the mail with a hashed token', function (): void {
    $account = BunnyAccount::factory()->create();

    $this->actingAsPanelUser($this->admin);

    Livewire::test(Invite::class)
        ->set('email', 'new-member@example.com')
        ->set('name', 'New Member')
        ->set('role', UserRole::Member->value)
        ->call('addGrantRow')
        ->set('grantsForm.rows.0.zone', "{$account->id}:example.com")
        ->set('grantsForm.rows.0.root_mode', ZoneGrantsForm::MODE_SELECTED)
        ->set('grantsForm.rows.0.root_types', [DnsEntryType::TXT->value])
        ->set('grantsForm.rows.0.subdomain_mode', ZoneGrantsForm::MODE_ALL)
        ->call('invite')
        ->assertHasNoErrors()
        ->assertRedirect(route('bunny-sidecar.users.index'));

    $invitation = Invitation::query()->firstOrFail();

    expect($invitation->email)->toBe('new-member@example.com')
        ->and($invitation->role)->toBe(UserRole::Member)
        ->and($invitation->invited_by)->toBe($this->admin->id)
        ->and($invitation->zone_grants)->toHaveCount(1)
        ->and($invitation->zone_grants[0]['zone_domain'])->toBe('example.com')
        ->and($invitation->zone_grants[0]['permissions']['root'])->toBe([DnsEntryType::TXT->value])
        ->and($invitation->zone_grants[0]['permissions']['subdomain'])->toBe('*')
        ->and(mb_strlen($invitation->token))->toBe(64);

    Mail::assertSent(UserInvitationMail::class, function (UserInvitationMail $mail) use ($invitation): bool {
        return $mail->hasTo('new-member@example.com')
            && hash('sha256', $mail->plainToken) === $invitation->token;
    });
});

test('the invitation mail links to the panel accept page', function (): void {
    $this->actingAsPanelUser($this->admin);

    Livewire::test(Invite::class)
        ->set('email', 'new-member@example.com')
        ->call('invite')
        ->assertHasNoErrors();

    Mail::assertSent(UserInvitationMail::class, function (UserInvitationMail $mail): bool {
        $rendered = $mail->render();

        return str_contains($rendered, url('/bunny/invitation/'.$mail->plainToken));
    });
});

test('invitation mails are queued when configured', function (): void {
    config(['bunny-sidecar.mail.queue' => true]);

    $this->actingAsPanelUser($this->admin);

    Livewire::test(Invite::class)
        ->set('email', 'queued@example.com')
        ->call('invite')
        ->assertHasNoErrors();

    Mail::assertQueued(UserInvitationMail::class);
    Mail::assertNotSent(UserInvitationMail::class);
});

test('inviting an admin stores no zone grants', function (): void {
    $this->actingAsPanelUser($this->admin);

    Livewire::test(Invite::class)
        ->set('email', 'new-admin@example.com')
        ->set('role', UserRole::Admin->value)
        ->call('invite')
        ->assertHasNoErrors();

    expect(Invitation::query()->firstOrFail()->zone_grants)->toBeNull();

    Mail::assertSent(UserInvitationMail::class);
});

test('rejects emails of existing users and pending invitations', function (): void {
    User::factory()->create(['email' => 'existing@example.com']);
    Invitation::factory()->create(['email' => 'pending@example.com']);

    $this->actingAsPanelUser($this->admin);

    Livewire::test(Invite::class)
        ->set('email', 'existing@example.com')
        ->call('invite')
        ->assertHasErrors(['email']);

    Livewire::test(Invite::class)
        ->set('email', 'pending@example.com')
        ->call('invite')
        ->assertHasErrors(['email']);

    Mail::assertNothingSent();
});

test('resending regenerates the token and extends the expiry', function (): void {
    $invitation = Invitation::factory()->create(['expires_at' => now()->addDay()]);
    $oldToken = $invitation->token;

    $this->actingAsPanelUser($this->admin);

    Livewire::test(Index::class)
        ->call('resendInvitation', $invitation->id)
        ->assertHasNoErrors();

    $invitation->refresh();

    expect($invitation->token)->not->toBe($oldToken)
        ->and($invitation->expires_at->isAfter(now()->addDays(6)))->toBeTrue();

    Mail::assertSent(UserInvitationMail::class);
});

test('revoking deletes the invitation', function (): void {
    $invitation = Invitation::factory()->create();

    $this->actingAsPanelUser($this->admin);

    Livewire::test(Index::class)
        ->call('revokeInvitation', $invitation->id)
        ->assertHasNoErrors();

    $this->assertDatabaseMissing('bunny_sidecar_invitations', ['id' => $invitation->id]);
});
