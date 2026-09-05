<div align="center">
    <h1>Bunny Sidecar</h1>
</div>

<p align="center">
    <a href="https://packagist.org/packages/bastihilger/bunny-sidecar"><img src="https://img.shields.io/packagist/v/bastihilger/bunny-sidecar.svg?style=flat-square" alt="Packagist"></a>
    <a href="https://packagist.org/packages/bastihilger/bunny-sidecar"><img src="https://img.shields.io/packagist/php-v/bastihilger/bunny-sidecar.svg?style=flat-square" alt="PHP from Packagist"></a>
    <a href="https://badge.laravel.cloud/badge/bastihilger/bunny-sidecar?style=flat"><img src="https://badge.laravel.cloud/badge/bastihilger/bunny-sidecar?style=flat" alt="Laravel versions"></a>
    <a href="https://github.com/bastihilger/bunny-sidecar/actions"><img alt="GitHub Workflow Status (main)" src="https://img.shields.io/github/actions/workflow/status/bastihilger/bunny-sidecar/tests.yml?branch=main&label=Tests&style=flat-square"></a>
    <a href="https://packagist.org/packages/bastihilger/bunny-sidecar"><img src="https://img.shields.io/packagist/dt/bastihilger/bunny-sidecar.svg?style=flat-square" alt="Total Downloads"></a>
</p>

Share selected [bunny.net](https://bunny.net) DNS zones with people who shouldn't see the rest of your bunny.net account, from a self-contained panel inside your Laravel application.

bunny.net has no built-in way to give someone access to just a few DNS zones. Bunny Sidecar wraps the bunny.net API behind its own user management, mounted under one URL prefix of your app (like Filament):

- **Admins** add bunny.net accounts (name + API key), invite users by email, review WAF (Shield) events, and manage everything.
- **Members** only see the DNS zones an admin connected to them — read-only by default, with granular write grants per zone: for each scope (zone root / subdomains) either all record types or a selected subset. Members may edit and delete the records they created; admins can additionally assign them as managers of individual records.

Panel users live in their **own table and auth guard**. A member can log in to the panel but never to your application, and your application's users have no access to the panel.

DNS zones and records are always read live from the bunny.net API (with short caches). Only panel users, bunny.net accounts, zone grants, and invitations are stored locally. API keys are stored encrypted.

## Requirements

- PHP 8.3+
- Laravel 12 or 13
- A database and a configured mailer (invitation and password reset mails)

The UI is built with [Livewire](https://livewire.laravel.com) and the free [Flux UI](https://fluxui.dev) components; both are installed as dependencies. No Node.js build is needed in your application: the stylesheet ships precompiled.

## Installation

```bash
composer require bastihilger/bunny-sidecar
php artisan bunny-sidecar:install
```

The installer publishes the config file and migrations, runs the migrations, and creates the first admin. Everyone else joins by invitation. Then open `/bunny/login`.

You can also run the steps individually:

```bash
php artisan vendor:publish --tag="bunny-sidecar-config"
php artisan vendor:publish --tag="bunny-sidecar-migrations"
php artisan migrate
php artisan bunny-sidecar:create-admin
```

Optional publish tags: `bunny-sidecar-views`, `bunny-sidecar-lang`, or `bunny-sidecar` for everything.

## Usage

1. Log in as admin and add a bunny.net account under **Bunny Accounts** (the API key from the bunny.net dashboard).
2. **Users → Invite user**: invite an admin, or invite a member and connect DNS zones with per-scope record type permissions.
3. The invitee receives an email link, sets a password, and sees exactly the zones granted to them.
4. On a zone page, admins can assign members as **managers** of individual records they did not create.
5. **WAF Events** lists the Shield events a WAF rule produced, including the matched request data the bunny.net dashboard hides.

## Configuration

`config/bunny-sidecar.php` after publishing:

| Key | Default | Purpose |
| --- | --- | --- |
| `path` | `bunny` | URI prefix of the panel (`/bunny/login`) |
| `domain` | `null` | Optional domain the panel routes are bound to |
| `middleware` | `['web']` | Middleware wrapping every panel route |
| `name` | `Bunny Sidecar` | Brand shown next to the logo, in page titles, and in mails |
| `guard` | `bunny-sidecar` | Name of the auth guard, provider and password broker the package registers |
| `mail.queue` | `false` | Queue invitation mails instead of sending them synchronously |
| `api.base_url` | `https://api.bunny.net` | bunny.net API base URL |
| `api.zones_ttl` | `300` | Seconds the zone list per account is cached |
| `api.records_ttl` | `20` | Seconds a zone's records are cached |
| `api.shield_events_hours` | `72` | Hours of Shield event logs searched |

The guard, user provider, and password broker are only registered if your `config/auth.php` does not already define entries under the configured name, so you can override them there.

## Authentication model

- Panel users are `BunnySidecar\BunnySidecar\Models\User` rows in `bunny_sidecar_users`, authenticated by the `bunny-sidecar` session guard.
- The panel's own `auth`, `guest`, and `admin` middleware are bound to that guard. Your app's `auth` middleware never accepts a panel user, and a user of your app is never accepted by the panel.
- Within panel requests the package guard becomes the default guard, so `auth()->user()` resolves the panel user there.

## Commands

| Command | Purpose |
| --- | --- |
| `bunny-sidecar:install` | Publish config and migrations, migrate, create the first admin (`--no-migrate`, `--no-admin`) |
| `bunny-sidecar:create-admin` | Create an admin user; prompts for `--name`, `--email`, `--password` when omitted |

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Thank you for considering contributing to Bunny Sidecar! Please review our [contributing guide](.github/CONTRIBUTING.md) to get started.

## Security Vulnerabilities

Please review [our security policy](.github/SECURITY.md) on how to report security vulnerabilities.

## Credits

- [Sebastian Hilger](https://github.com/bastihilger)
- [All Contributors](../../contributors)

## License

Bunny Sidecar is open-sourced software licensed under the [MIT license](LICENSE.md).
