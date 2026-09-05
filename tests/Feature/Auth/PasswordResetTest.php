<?php

declare(strict_types=1);

use BunnySidecar\BunnySidecar\Livewire\Auth\ForgotPassword;
use BunnySidecar\BunnySidecar\Livewire\Auth\ResetPassword;
use BunnySidecar\BunnySidecar\Models\User;
use BunnySidecar\BunnySidecar\Notifications\ResetPasswordNotification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

test('the reset link screen renders', function (): void {
    $this->get(route('bunny-sidecar.password.request'))->assertOk();
});

test('a reset link can be requested and points at the panel reset page', function (): void {
    Notification::fake();

    $user = User::factory()->create();

    Livewire::test(ForgotPassword::class)
        ->set('email', $user->email)
        ->call('sendPasswordResetLink')
        ->assertHasNoErrors();

    Notification::assertSentTo($user, ResetPasswordNotification::class, function (ResetPasswordNotification $notification) use ($user): bool {
        expect($notification->resetUrl($user))
            ->toStartWith(url('/bunny/password/reset/'))
            ->toContain('email='.urlencode($user->email));

        $this->get(route('bunny-sidecar.password.reset', ['token' => $notification->token, 'email' => $user->email]))
            ->assertOk()
            ->assertSee($user->email);

        return true;
    });
});

test('requesting a link for an unknown email gives the same answer', function (): void {
    Notification::fake();

    Livewire::test(ForgotPassword::class)
        ->set('email', 'nobody@example.com')
        ->call('sendPasswordResetLink')
        ->assertHasNoErrors();

    Notification::assertNothingSent();
});

test('the password can be reset with a valid token', function (): void {
    Notification::fake();

    $user = User::factory()->create();

    Livewire::test(ForgotPassword::class)
        ->set('email', $user->email)
        ->call('sendPasswordResetLink');

    Notification::assertSentTo($user, ResetPasswordNotification::class, function (ResetPasswordNotification $notification) use ($user): bool {
        Livewire::test(ResetPassword::class, ['token' => $notification->token])
            ->set('email', $user->email)
            ->set('password', 'new-password')
            ->set('password_confirmation', 'new-password')
            ->call('resetPassword')
            ->assertHasNoErrors()
            ->assertRedirect(route('bunny-sidecar.login'));

        expect(Hash::check('new-password', $user->refresh()->password))->toBeTrue();

        return true;
    });
});

test('an invalid token is rejected', function (): void {
    $user = User::factory()->create();

    Livewire::test(ResetPassword::class, ['token' => 'bogus'])
        ->set('email', $user->email)
        ->set('password', 'new-password')
        ->set('password_confirmation', 'new-password')
        ->call('resetPassword')
        ->assertHasErrors(['email']);

    expect(Hash::check('password', $user->refresh()->password))->toBeTrue();
});
