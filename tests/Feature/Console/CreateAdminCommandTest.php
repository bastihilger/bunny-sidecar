<?php

declare(strict_types=1);

use BunnySidecar\BunnySidecar\Enums\UserRole;
use BunnySidecar\BunnySidecar\Models\User;
use Illuminate\Support\Facades\Hash;

test('creates an admin user from options', function (): void {
    $this->artisan('bunny-sidecar:create-admin', [
        '--name' => 'Jane Admin',
        '--email' => 'jane@example.com',
        '--password' => 'secret-password',
    ])->assertSuccessful();

    $user = User::query()->where('email', 'jane@example.com')->firstOrFail();

    expect($user->role)->toBe(UserRole::Admin)
        ->and(Hash::check('secret-password', $user->password))->toBeTrue();
});

test('prompts for missing input', function (): void {
    $this->artisan('bunny-sidecar:create-admin')
        ->expectsQuestion('Name', 'Prompted Admin')
        ->expectsQuestion('Email address', 'prompted@example.com')
        ->expectsQuestion('Password', 'secret-password')
        ->assertSuccessful();

    expect(User::query()->where('email', 'prompted@example.com')->firstOrFail()->isAdmin())->toBeTrue();
});

test('fails for a duplicate email', function (): void {
    User::factory()->create(['email' => 'taken@example.com']);

    $this->artisan('bunny-sidecar:create-admin', [
        '--name' => 'Jane Admin',
        '--email' => 'taken@example.com',
        '--password' => 'secret-password',
    ])->assertFailed();

    expect(User::query()->count())->toBe(1);
});

test('fails for an invalid email', function (): void {
    $this->artisan('bunny-sidecar:create-admin', [
        '--name' => 'Jane Admin',
        '--email' => 'not-an-email',
        '--password' => 'secret-password',
    ])->assertFailed();

    expect(User::query()->count())->toBe(0);
});
