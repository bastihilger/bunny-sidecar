<?php

declare(strict_types=1);

namespace BunnySidecar\BunnySidecar;

use BunnySidecar\BunnySidecar\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Support\Facades\Auth;

/**
 * Entry point for the panel's runtime settings and its own auth guard.
 */
class BunnySidecar
{
    /**
     * The name of the session guard panel users authenticate with.
     */
    public function guard(): string
    {
        return config()->string('bunny-sidecar.guard');
    }

    public function auth(): StatefulGuard
    {
        return Auth::guard($this->guard());
    }

    /**
     * The panel user of the current request, if any.
     */
    public function user(): ?User
    {
        $user = $this->auth()->user();

        return $user instanceof User ? $user : null;
    }

    /**
     * The panel user of the current request.
     *
     * @throws AuthenticationException
     */
    public function currentUser(): User
    {
        return $this->user() ?? throw new AuthenticationException('Unauthenticated.', [$this->guard()], $this->loginUrl());
    }

    /**
     * The brand name shown in the panel and in mails.
     */
    public function name(): string
    {
        $name = config('bunny-sidecar.name');

        return is_string($name) && $name !== '' ? $name : config()->string('app.name', 'Laravel');
    }

    public function path(): string
    {
        return trim(config()->string('bunny-sidecar.path', 'bunny'), '/');
    }

    public function loginUrl(): string
    {
        return route('bunny-sidecar.login');
    }

    public function homeUrl(): string
    {
        return route('bunny-sidecar.zones.index');
    }

    /**
     * The versioned URL of the panel's precompiled stylesheet.
     */
    public function stylesheetUrl(): string
    {
        $modified = filemtime($this->stylesheetPath());

        return route('bunny-sidecar.assets.css', ['v' => $modified === false ? 0 : $modified]);
    }

    public function stylesheetPath(): string
    {
        return dirname(__DIR__).'/resources/dist/bunny-sidecar.css';
    }

    /**
     * Route group attributes for every panel route.
     *
     * @return array<string, mixed>
     */
    public function routeGroupAttributes(): array
    {
        $middleware = config('bunny-sidecar.middleware', ['web']);
        $domain = config('bunny-sidecar.domain');

        return array_filter([
            'prefix' => $this->path(),
            'domain' => is_string($domain) && $domain !== '' ? $domain : null,
            'middleware' => is_array($middleware) ? $middleware : ['web'],
            'as' => 'bunny-sidecar.',
        ], fn (mixed $value): bool => $value !== null);
    }
}
