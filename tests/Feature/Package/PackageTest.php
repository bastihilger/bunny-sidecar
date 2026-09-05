<?php

declare(strict_types=1);

use BunnySidecar\BunnySidecar\Access\RecordManagers;
use BunnySidecar\BunnySidecar\Access\RecordOwnerships;
use BunnySidecar\BunnySidecar\Access\ZoneAccess;
use BunnySidecar\BunnySidecar\BunnySidecar;
use BunnySidecar\BunnySidecar\BunnySidecarServiceProvider;
use BunnySidecar\BunnySidecar\Facades\BunnySidecar as BunnySidecarFacade;
use Livewire\Features\SupportDisablingBackButtonCache\SupportDisablingBackButtonCache;
use Livewire\Livewire;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

test('the package services are bound', function (): void {
    expect(app(BunnySidecar::class))->toBe(app(BunnySidecar::class))
        ->and(app(ZoneAccess::class))->toBe(app(ZoneAccess::class))
        ->and(app(RecordOwnerships::class))->toBeInstanceOf(RecordOwnerships::class)
        ->and(app(RecordManagers::class))->toBeInstanceOf(RecordManagers::class)
        ->and(BunnySidecarFacade::path())->toBe('bunny');
});

test('the config is merged with sane defaults', function (): void {
    expect(config('bunny-sidecar.path'))->toBe('bunny')
        ->and(config('bunny-sidecar.api.base_url'))->toBe('https://api.bunny.net')
        ->and(config('bunny-sidecar.api.zones_ttl'))->toBe(300)
        ->and(config('bunny-sidecar.mail.queue'))->toBeFalse();
});

test('the brand name falls back to the application name', function (): void {
    config(['app.name' => 'Host App', 'bunny-sidecar.name' => null]);
    expect(app(BunnySidecar::class)->name())->toBe('Host App');

    config(['bunny-sidecar.name' => 'DNS Desk']);
    expect(app(BunnySidecar::class)->name())->toBe('DNS Desk');
});

test('every panel route lives under the prefix', function (): void {
    foreach (['login', 'password.request', 'zones.index', 'accounts.index', 'shield.events', 'users.index', 'users.invite', 'profile.edit', 'password.edit'] as $name) {
        expect(route("bunny-sidecar.{$name}", absolute: false))->toStartWith('/bunny/');
    }
});

test('the precompiled stylesheet and favicon are served from the package', function (): void {
    // Livewire flags responses after a component rendered; reset the static
    // flag so a page test earlier in the process cannot clobber the headers.
    SupportDisablingBackButtonCache::$disableBackButtonCache = false;

    $sidecar = app(BunnySidecar::class);

    expect($sidecar->stylesheetUrl())->toContain('/bunny-sidecar/assets/bunny-sidecar.css?v=')
        ->and($sidecar->faviconUrl())->toContain('/bunny-sidecar/assets/favicon.svg?v=');

    $this->get($sidecar->stylesheetUrl())
        ->assertOk()
        ->assertHeader('Content-Type', 'text/css; charset=utf-8')
        ->assertHeader('Cache-Control', 'immutable, max-age=31536000, public');

    $favicon = $this->get($sidecar->faviconUrl())
        ->assertOk()
        ->assertHeader('Content-Type', 'image/svg+xml')
        ->baseResponse;

    expect($favicon)->toBeInstanceOf(BinaryFileResponse::class)
        ->and($favicon->getFile()->getFilename())->toBe('favicon.svg');

    $this->get('/bunny-sidecar/assets/composer.json')->assertNotFound();
});

test('every panel page links the carrot favicon', function (): void {
    $this->get(route('bunny-sidecar.login'))
        ->assertOk()
        ->assertSee('rel="icon" href="'.url('/bunny-sidecar/assets/favicon.svg'), escape: false);
});

test('all livewire components are registered under their aliases', function (): void {
    foreach (BunnySidecarServiceProvider::COMPONENTS as $alias => $class) {
        expect(Livewire::isDiscoverable($alias))->toBeTrue("[{$alias}] should resolve to {$class}");
    }
});
