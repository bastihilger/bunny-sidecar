<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Panel
    |--------------------------------------------------------------------------
    |
    | The URI prefix (and optional domain) under which the Bunny Sidecar panel
    | is served. With the defaults the login page lives at /bunny/login. The
    | middleware wraps every panel route; the package adds its own auth guard.
    | Publish this file (php artisan bunny-sidecar:install does) to change
    | any of these values, using env() there if you like.
    |
    */

    'path' => 'bunny',

    'domain' => null,

    'middleware' => ['web'],

    /*
    |--------------------------------------------------------------------------
    | Brand
    |--------------------------------------------------------------------------
    |
    | Shown next to the logo in the navigation, in page titles, and in emails.
    |
    */

    'name' => 'Bunny Sidecar',

    /*
    |--------------------------------------------------------------------------
    | Authentication
    |--------------------------------------------------------------------------
    |
    | Panel users live in their own table and authenticate through their own
    | session guard, so members can never log in anywhere else in the host
    | application. The guard, provider and password broker are registered by
    | the package under this name unless the host app already defines them.
    |
    */

    'guard' => 'bunny-sidecar',

    'password_reset' => [
        'expire' => 60,
        'throttle' => 60,
    ],

    /*
    |--------------------------------------------------------------------------
    | Two-Factor Authentication
    |--------------------------------------------------------------------------
    |
    | Panel users may protect their account with a TOTP authenticator app
    | (Google Authenticator, 1Password, ...) plus recovery codes. Disable the
    | feature to hide the settings page and skip the challenge at login. The
    | window is the number of 30-second steps accepted around the current one.
    |
    */

    'two_factor' => [
        'enabled' => true,
        'window' => 1,
    ],

    /*
    |--------------------------------------------------------------------------
    | Mail
    |--------------------------------------------------------------------------
    |
    | Invitation mails are queued when enabled; otherwise they are sent
    | synchronously so no queue worker is required.
    |
    */

    'mail' => [
        'queue' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | bunny.net API
    |--------------------------------------------------------------------------
    |
    | Zones and records are always read live from bunny.net with short caches.
    | TTLs are seconds. bunny.net retains Shield event logs for 72 hours.
    |
    */

    'api' => [
        'base_url' => 'https://api.bunny.net',
        'zones_ttl' => 300,
        'records_ttl' => 20,
        'shield_events_hours' => 72,
        'shield_rules_ttl' => 120,
        'shield_events_page_size' => 100,
        'shield_events_max_pages' => 10,
    ],

];
