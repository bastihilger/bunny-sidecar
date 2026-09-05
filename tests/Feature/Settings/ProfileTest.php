<?php

declare(strict_types=1);

use BunnySidecar\BunnySidecar\Livewire\Settings\Password;
use BunnySidecar\BunnySidecar\Livewire\Settings\Profile;
use BunnySidecar\BunnySidecar\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

test('the settings pages are displayed to members and admins alike', function (): void {
    $this->actingAsPanelUser(User::factory()->member()->create());

    $this->get(route('bunny-sidecar.profile.edit'))->assertOk();
    $this->get(route('bunny-sidecar.password.edit'))->assertOk();
});

test('profile information can be updated', function (): void {
    $user = User::factory()->create();

    $this->actingAsPanelUser($user);

    Livewire::test(Profile::class)
        ->assertSet('email', $user->email)
        ->set('name', 'Test User')
        ->set('email', 'test@example.com')
        ->call('updateProfileInformation')
        ->assertHasNoErrors();

    $user->refresh();

    expect($user->name)->toBe('Test User')
        ->and($user->email)->toBe('test@example.com');
});

test('the email must be unique among panel users', function (): void {
    User::factory()->create(['email' => 'taken@example.com']);
    $user = User::factory()->create();

    $this->actingAsPanelUser($user);

    Livewire::test(Profile::class)
        ->set('email', 'taken@example.com')
        ->call('updateProfileInformation')
        ->assertHasErrors(['email']);
});

test('the password can be changed with the current password', function (): void {
    $user = User::factory()->create();

    $this->actingAsPanelUser($user);

    Livewire::test(Password::class)
        ->set('current_password', 'password')
        ->set('password', 'new-password-123')
        ->set('password_confirmation', 'new-password-123')
        ->call('updatePassword')
        ->assertHasNoErrors();

    expect(Hash::check('new-password-123', $user->refresh()->password))->toBeTrue();
});

test('the current password must be correct', function (): void {
    $user = User::factory()->create();

    $this->actingAsPanelUser($user);

    Livewire::test(Password::class)
        ->set('current_password', 'wrong-password')
        ->set('password', 'new-password-123')
        ->set('password_confirmation', 'new-password-123')
        ->call('updatePassword')
        ->assertHasErrors(['current_password']);

    expect(Hash::check('password', $user->refresh()->password))->toBeTrue();
});
