<?php

declare(strict_types=1);

use BunnySidecar\BunnySidecar\Livewire\Auth\Login;
use BunnySidecar\BunnySidecar\Models\User;
use Livewire\Livewire;

test('the login screen renders under the panel prefix', function (): void {
    expect(route('bunny-sidecar.login', absolute: false))->toBe('/bunny/login');

    $this->get(route('bunny-sidecar.login'))
        ->assertOk()
        ->assertSee(__('Log in to your account'));
});

test('users can authenticate on the panel guard', function (): void {
    $user = User::factory()->create();

    Livewire::test(Login::class)
        ->set('email', $user->email)
        ->set('password', 'password')
        ->call('login')
        ->assertHasNoErrors()
        ->assertRedirect(route('bunny-sidecar.zones.index'));

    $this->assertAuthenticatedAs($user, 'bunny-sidecar');
    $this->assertGuest('web');
});

test('users cannot authenticate with an invalid password', function (): void {
    $user = User::factory()->create();

    Livewire::test(Login::class)
        ->set('email', $user->email)
        ->set('password', 'wrong-password')
        ->call('login')
        ->assertHasErrors(['email']);

    $this->assertGuest('bunny-sidecar');
});

test('login attempts are rate limited', function (): void {
    $user = User::factory()->create();

    foreach (range(1, 5) as $attempt) {
        Livewire::test(Login::class)
            ->set('email', $user->email)
            ->set('password', 'wrong-password')
            ->call('login');
    }

    Livewire::test(Login::class)
        ->set('email', $user->email)
        ->set('password', 'password')
        ->call('login')
        ->assertHasErrors(['email']);

    $this->assertGuest('bunny-sidecar');
});

test('authenticated users are redirected away from the login page', function (): void {
    $this->actingAsPanelUser(User::factory()->create());

    $this->get(route('bunny-sidecar.login'))->assertRedirect(route('bunny-sidecar.zones.index'));
});

test('the home route sends guests to the login and users to the zones', function (): void {
    $this->get(route('bunny-sidecar.home'))->assertRedirect(route('bunny-sidecar.login'));

    $this->actingAsPanelUser(User::factory()->create());

    $this->get(route('bunny-sidecar.home'))->assertRedirect(route('bunny-sidecar.zones.index'));
});

test('users can log out of the panel', function (): void {
    $user = User::factory()->create();

    $this->actingAsPanelUser($user)
        ->post(route('bunny-sidecar.logout'))
        ->assertRedirect(route('bunny-sidecar.login'));

    $this->assertGuest('bunny-sidecar');
});
