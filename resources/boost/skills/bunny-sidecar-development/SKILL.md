---
name: bunny-sidecar-development
description: >
  Install, configure, and operate the Bunny Sidecar panel (bunny.net DNS zone
  sharing with its own users and guard) in a Laravel application.
license: MIT
metadata:
  author: Sebastian Hilger
---

# Bunny Sidecar

Use this skill when a Laravel application needs to integrate the `bastihilger/bunny-sidecar` package: a panel that shares selected bunny.net DNS zones with members who must not see the rest of the bunny.net account, and must not get access to the host application.

## Primary Goal

- install the panel with `composer require` plus one Artisan command, and adjust only the published config

## Workflow

### 1. Install

```bash
composer require bastihilger/bunny-sidecar
php artisan bunny-sidecar:install
```

The installer publishes `config/bunny-sidecar.php` and the migration, runs `migrate`, and prompts for the first admin. Use `--no-migrate` / `--no-admin` in scripted setups and run `php artisan bunny-sidecar:create-admin --name= --email= --password=` yourself.

### 2. Configure

Edit the published `config/bunny-sidecar.php`:

- `path` (default `bunny`): the panel lives at `/{path}`, login at `/{path}/login`. `domain` and `middleware` (default `['web']`) adjust the route group.
- `name`: brand in the panel and mails; `null` falls back to `app.name`.
- `guard` (default `bunny-sidecar`): the package registers `auth.guards.{guard}`, `auth.providers.{guard}` (Eloquent, `BunnySidecar\BunnySidecar\Models\User`) and `auth.passwords.{guard}` unless the app already defines them.
- `mail.queue`: set `true` only when a queue worker runs; otherwise mails are sent synchronously.
- `api.*`: bunny.net base URL and cache TTLs.

A mailer must be configured; invitations and password resets are delivered by mail.

### 3. Operate

- Admins add bunny.net accounts (API key stored encrypted), invite users, and connect DNS zones with per-scope record type grants to members.
- Members log in at `/{path}/login` only. They can never authenticate against the host app's guard, and host app users cannot enter the panel.
- Routes are named `bunny-sidecar.*` (`bunny-sidecar.login`, `bunny-sidecar.zones.index`, ...). Link to the panel with `route('bunny-sidecar.login')`.

### 4. Verify in the consuming app

```php
$this->get(route('bunny-sidecar.login'))->assertOk();

$admin = \BunnySidecar\BunnySidecar\Models\User::query()->create([
    'name' => 'Admin', 'email' => 'admin@example.com', 'password' => 'secret', 'role' => \BunnySidecar\BunnySidecar\Enums\UserRole::Admin,
]);

$this->actingAs($admin, 'bunny-sidecar')->get(route('bunny-sidecar.zones.index'))->assertOk();
```

## Rules, References, and Templates

Read before executing:

- the package README for the full config table and publish tags (`bunny-sidecar-config`, `bunny-sidecar-migrations`, `bunny-sidecar-views`, `bunny-sidecar-lang`)

## Examples

- Move the panel to `/dns` on `admin.example.com`: set `path` to `dns` and `domain` to `admin.example.com` in the published config, then clear the route cache.
- Queue invitation mails: set `mail.queue` to `true` and run `php artisan queue:work`.

## Anti-patterns

- do not add panel users to the host app's `users` table or guard; the isolation is the point of the package
- do not build frontend assets for the panel; the stylesheet is served by the package itself
- do not publish and edit the views unless the app needs custom markup; config covers path, domain, brand, and middleware
