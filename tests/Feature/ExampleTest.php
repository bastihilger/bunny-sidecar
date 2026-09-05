<?php

declare(strict_types=1);

use BunnySidecar\BunnySidecar\BunnySidecar;

it('resolves the singleton', function () {
    expect(app(BunnySidecar::class))->toBeInstanceOf(BunnySidecar::class);
});

it('returns the same instance from the container', function () {
    expect(app(BunnySidecar::class))->toBe(app(BunnySidecar::class));
});

it('merges the package config', function () {
    expect(config('bunny-sidecar.placeholder'))->toBe('default');
});

it('loads the package translations', function () {
    expect(trans('bunny-sidecar::messages.placeholder'))->toBe('BunnySidecar placeholder translation.');
});

it('loads the package views', function () {
    expect(view()->exists('bunny-sidecar::placeholder'))->toBeTrue();
});

it('registers the artisan command', function () {
    $this->artisan('bunny-sidecar:placeholder')
        ->expectsOutputToContain('BunnySidecar placeholder command executed.')
        ->assertSuccessful();
});
