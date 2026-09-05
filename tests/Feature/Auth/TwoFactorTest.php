<?php

declare(strict_types=1);

use BunnySidecar\BunnySidecar\Livewire\Auth\Login;
use BunnySidecar\BunnySidecar\Livewire\Auth\TwoFactorChallenge;
use BunnySidecar\BunnySidecar\Livewire\Settings\TwoFactor;
use BunnySidecar\BunnySidecar\Models\User;
use Livewire\Livewire;
use PragmaRX\Google2FA\Google2FA;

function currentOtp(User $user): string
{
    return app(Google2FA::class)->getCurrentOtp((string) $user->refresh()->two_factor_secret);
}

test('the two-factor settings page is shown and hidden by config', function (): void {
    $this->actingAsPanelUser(User::factory()->create());

    $this->get(route('bunny-sidecar.two-factor.edit'))->assertOk()->assertSee(__('Enable two-factor authentication'));
    $this->get(route('bunny-sidecar.profile.edit'))->assertOk()->assertSee(__('Two-factor'));

    config(['bunny-sidecar.two_factor.enabled' => false]);

    $this->get(route('bunny-sidecar.two-factor.edit'))->assertNotFound();
    $this->get(route('bunny-sidecar.profile.edit'))->assertOk()->assertDontSee(__('Two-factor'));
});

test('enabling shows a qr code and confirming with a valid code activates it', function (): void {
    $user = User::factory()->create();
    $this->actingAsPanelUser($user);

    $component = Livewire::test(TwoFactor::class)
        ->call('enable')
        ->assertSee('<svg', escape: false)
        ->assertSee((string) $user->refresh()->two_factor_secret);

    expect($user->hasPendingTwoFactorAuthentication())->toBeTrue()
        ->and($user->hasEnabledTwoFactorAuthentication())->toBeFalse();

    $component
        ->set('code', '000000')
        ->call('confirm')
        ->assertHasErrors(['code']);

    expect($user->refresh()->two_factor_confirmed_at)->toBeNull();

    $component
        ->set('code', currentOtp($user))
        ->call('confirm')
        ->assertHasNoErrors()
        ->assertSee(__('Recovery codes'));

    expect($user->refresh()->hasEnabledTwoFactorAuthentication())->toBeTrue()
        ->and($user->two_factor_recovery_codes)->toHaveCount(8);
});

test('the secret and recovery codes are stored encrypted and hidden', function (): void {
    $user = User::factory()->withTwoFactor()->create();

    $raw = DB::table('bunny_sidecar_users')->where('id', $user->id)->first();

    expect($raw?->two_factor_secret)->not->toBe($user->two_factor_secret)
        ->and($raw?->two_factor_recovery_codes)->not->toContain('recovery-code-1')
        ->and($user->toArray())->not->toHaveKeys(['two_factor_secret', 'two_factor_recovery_codes']);
});

test('cancelling a pending setup removes the secret again', function (): void {
    $user = User::factory()->create();
    $this->actingAsPanelUser($user);

    Livewire::test(TwoFactor::class)->call('enable')->call('cancel');

    expect($user->refresh()->two_factor_secret)->toBeNull();
});

test('recovery codes and disabling require the current password', function (): void {
    $user = User::factory()->withTwoFactor()->create();
    $this->actingAsPanelUser($user);

    Livewire::test(TwoFactor::class)
        ->assertDontSee('recovery-code-1')
        ->set('password', 'wrong')
        ->call('showRecoveryCodes')
        ->assertHasErrors(['password'])
        ->assertDontSee('recovery-code-1')
        ->set('password', 'password')
        ->call('showRecoveryCodes')
        ->assertHasNoErrors()
        ->assertSee('recovery-code-1')
        ->set('password', 'password')
        ->call('regenerateRecoveryCodes')
        ->assertDontSee('recovery-code-1');

    expect($user->refresh()->two_factor_recovery_codes)->toHaveCount(8)->not->toContain('recovery-code-1');

    Livewire::test(TwoFactor::class)
        ->set('password', 'wrong')
        ->call('disable')
        ->assertHasErrors(['password']);

    expect($user->refresh()->hasEnabledTwoFactorAuthentication())->toBeTrue();

    Livewire::test(TwoFactor::class)
        ->set('password', 'password')
        ->call('disable')
        ->assertHasNoErrors();

    expect($user->refresh()->two_factor_secret)->toBeNull()
        ->and($user->two_factor_recovery_codes)->toBeNull()
        ->and($user->two_factor_confirmed_at)->toBeNull();
});

test('users with two-factor enabled are sent to the challenge instead of being logged in', function (): void {
    $user = User::factory()->withTwoFactor()->create();

    Livewire::test(Login::class)
        ->set('email', $user->email)
        ->set('password', 'password')
        ->set('remember', true)
        ->call('login')
        ->assertHasNoErrors()
        ->assertRedirect(route('bunny-sidecar.two-factor.login'));

    $this->assertGuest('bunny-sidecar');
    expect(session(TwoFactorChallenge::SESSION_USER))->toBe($user->id)
        ->and(session(TwoFactorChallenge::SESSION_REMEMBER))->toBeTrue();
});

test('a wrong password never parks a login for the challenge', function (): void {
    $user = User::factory()->withTwoFactor()->create();

    Livewire::test(Login::class)
        ->set('email', $user->email)
        ->set('password', 'wrong-password')
        ->call('login')
        ->assertHasErrors(['email']);

    expect(session()->has(TwoFactorChallenge::SESSION_USER))->toBeFalse();
});

test('the challenge accepts a valid authenticator code and logs the user in', function (): void {
    $user = User::factory()->withTwoFactor()->create();
    session([TwoFactorChallenge::SESSION_USER => $user->id, TwoFactorChallenge::SESSION_REMEMBER => false]);

    $this->get(route('bunny-sidecar.two-factor.login'))->assertOk()->assertSee(__('Authentication code'));

    Livewire::test(TwoFactorChallenge::class)
        ->set('code', '000000')
        ->call('authenticate')
        ->assertHasErrors(['code']);

    $this->assertGuest('bunny-sidecar');

    Livewire::test(TwoFactorChallenge::class)
        ->set('code', currentOtp($user))
        ->call('authenticate')
        ->assertHasNoErrors()
        ->assertRedirect(route('bunny-sidecar.zones.index'));

    $this->assertAuthenticatedAs($user, 'bunny-sidecar');
    expect(session()->has(TwoFactorChallenge::SESSION_USER))->toBeFalse();
});

test('the challenge accepts a recovery code once and replaces it', function (): void {
    $user = User::factory()->withTwoFactor()->create();
    session([TwoFactorChallenge::SESSION_USER => $user->id]);

    Livewire::test(TwoFactorChallenge::class)
        ->set('recovery_code', 'recovery-code-3')
        ->call('authenticate')
        ->assertHasNoErrors()
        ->assertRedirect(route('bunny-sidecar.zones.index'));

    $this->assertAuthenticatedAs($user, 'bunny-sidecar');
    expect($user->refresh()->two_factor_recovery_codes)->toHaveCount(8)->not->toContain('recovery-code-3');

    auth('bunny-sidecar')->logout();
    session([TwoFactorChallenge::SESSION_USER => $user->id]);

    Livewire::test(TwoFactorChallenge::class)
        ->set('recovery_code', 'recovery-code-3')
        ->call('authenticate')
        ->assertHasErrors(['recovery_code']);

    $this->assertGuest('bunny-sidecar');
});

test('the challenge page is only reachable after the password step', function (): void {
    $this->get(route('bunny-sidecar.two-factor.login'))->assertRedirect(route('bunny-sidecar.login'));
});

test('two-factor is skipped at login when disabled in the config', function (): void {
    config(['bunny-sidecar.two_factor.enabled' => false]);
    $user = User::factory()->withTwoFactor()->create();

    Livewire::test(Login::class)
        ->set('email', $user->email)
        ->set('password', 'password')
        ->call('login')
        ->assertRedirect(route('bunny-sidecar.zones.index'));

    $this->assertAuthenticatedAs($user, 'bunny-sidecar');
});
