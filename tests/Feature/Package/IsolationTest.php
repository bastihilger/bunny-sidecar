<?php

declare(strict_types=1);

use BunnySidecar\BunnySidecar\BunnySidecar;
use BunnySidecar\BunnySidecar\Models\User;
use Illuminate\Foundation\Auth\User as HostUser;
use Illuminate\Support\Facades\Auth;

test('the package registers its own guard, provider and password broker', function (): void {
    expect(config('auth.guards.bunny-sidecar'))->toBe(['driver' => 'session', 'provider' => 'bunny-sidecar'])
        ->and(config('auth.providers.bunny-sidecar.model'))->toBe(User::class)
        ->and(config('auth.passwords.bunny-sidecar.table'))->toBe('bunny_sidecar_password_reset_tokens')
        ->and(app(BunnySidecar::class)->guard())->toBe('bunny-sidecar');
});

test('panel users never authenticate the host guard', function (): void {
    $user = User::factory()->create();

    expect(config('auth.providers.users.model'))->not->toBe(User::class)
        ->and(config('auth.guards.web.provider'))->toBe('users');

    $this->actingAsPanelUser($user);

    expect(Auth::guard('bunny-sidecar')->check())->toBeTrue()
        ->and(Auth::guard('web')->check())->toBeFalse();
});

test('a host application login grants no access to the panel', function (): void {
    $hostUser = new HostUser;
    $hostUser->forceFill(['id' => 1, 'name' => 'Host', 'email' => 'host@example.com']);

    $this->actingAs($hostUser, 'web');

    $this->get(route('bunny-sidecar.zones.index'))->assertRedirect(route('bunny-sidecar.login'));
});

test('guests are redirected to the panel login, not the host login', function (): void {
    $this->get(route('bunny-sidecar.zones.index'))->assertRedirect(route('bunny-sidecar.login'));
    $this->get(route('bunny-sidecar.accounts.index'))->assertRedirect(route('bunny-sidecar.login'));
});

test('the panel guard makes auth() resolve panel users inside panel requests', function (): void {
    $user = User::factory()->admin()->create();

    $this->actingAsPanelUser($user);

    $this->get(route('bunny-sidecar.users.index'))->assertOk()->assertSee($user->email);
});
