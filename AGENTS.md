# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

`CLAUDE.md` is a symlink to `AGENTS.md`, and `.claude/` is a symlink to `.agents/`. Edit `AGENTS.md` and `.agents/` directly; both names resolve to the same files.

# Bunny Sidecar

This repository is a Laravel package (`bastihilger/bunny-sidecar`, namespace `BunnySidecar\BunnySidecar`): a self-contained panel that shares selected bunny.net DNS zones with members, ported from the standalone app in `~/Code/bunny-visuell`. Keep the package focused, idiomatic, and easy for Laravel developers to install, test, and maintain.

## Package Conventions

- Use Laravel-native package APIs and the existing service provider shape before adding abstractions.
- Keep package names, namespaces, Composer metadata, publish tags, documentation, and examples aligned with `bastihilger/bunny-sidecar`.
- Add only the files and dependencies needed for the package behavior being implemented.
- Prefer explicit Laravel package code over helper abstractions unless the extension point is real.
- Keep tests focused on observable package behavior through public APIs, service provider wiring, commands, routes, published resources, and documentation promises.

## Commands

- Full validation (what CI runs, in this order): `composer test` = `analyse` + `lint:check` + `test:types` + `test:unit`
- Static analysis (PHPStan/Larastan level 7 over `src`, `config`, `database`, `routes`): `composer analyse`
- Formatting check / fix (Pint, `laravel` preset with extra rules in `pint.json`): `composer lint:check` / `composer lint`
- Type coverage, must be 100%: `composer test:types`
- Pest tests in parallel: `composer test:unit`
- Single test file: `vendor/bin/pest tests/Feature/ExampleTest.php`
- Single test by name: `vendor/bin/pest --filter="merges the package config"`
- One suite (`Arch`, `Feature`, `Unit`): `vendor/bin/pest --testsuite=Feature`
- Workbench build / dev server: `composer build` / `composer serve` (seeds admin@example.com / password, opens `/bunny`)
- Rebuild the panel stylesheet after changing Blade views or Flux usage: `npm install && npm run build` (writes the committed `resources/dist/bunny-sidecar.css`)
- Re-run package discovery after changing the provider or `composer.json` extra: `composer prepare` (also runs on every autoload dump)

## Architecture

Everything the host app sees is wired in `src/BunnySidecarServiceProvider.php`:

- `register()` merges the config, binds `BunnySidecar` (singleton) plus the request-scoped access services, and registers the panel's own session guard, Eloquent user provider, and password broker under the configured guard name unless the host already defines them.
- `boot()` loads views (`bunny-sidecar::`), translations, and `routes/bunny-sidecar.php`, registers every Livewire component under a `bunny-sidecar.*` alias (see `COMPONENTS`), marks the panel middleware as Livewire persistent middleware, and shares `$user` with the app layout. Console-only work (publish tags, commands) sits behind `runningInConsole()`.
- Publish tags: `bunny-sidecar-{config,views,lang,migrations}` plus the umbrella `bunny-sidecar`. There is no assets tag: `StylesheetController` serves the precompiled CSS.

Auth isolation is the core design: panel users are `Models\User` rows in `bunny_sidecar_users` on the `bunny-sidecar` guard. `Http\Middleware\Authenticate` requires that guard and calls `Auth::shouldUse()` so `auth()` resolves panel users inside panel requests; components still resolve the user through `BunnySidecar::currentUser()` (trait `ResolvesCurrentUser`) so `Livewire::test()` works without route middleware. Tests authenticate with `actingAsPanelUser()`.

Authorization is not Gate-based. `Access\ZoneAccess` encodes the member rules with an admin bypass; zones are not models but `(BunnyAccount, domain)` pairs. Grants hold one `Dtos\ZoneGrantPermissions` per zone (`{"root"|"subdomain": "*"|[DnsEntryType ints]}`); adding a type also allows managing records the member created (`RecordOwnerships`), and admins may assign explicit per-record managers (`RecordManagers`).

bunny.net data is never stored: read it through `Bunny\ZoneDirectory` / `Bunny\ShieldDirectory`, which cache plain arrays only (DTOs have `toArray()`/`fromArray()`) so hosts that forbid object unserialization work. Call `forgetRecords()` after record mutations and `forgetZones()` / `forgetShieldZones()` after account changes. Wrap `BunnyApiException` and show `userMessage()`, which never leaks API keys.

UI: class-based Livewire 4 components in `src/Livewire` with views in `resources/views/livewire`, layouts set via `#[Layout('bunny-sidecar::layouts.app')]`, free Flux components only (no Flux Pro). Flux must boot before Livewire (package discovery order) so its tag compiler runs ahead of Livewire's `wire:key` precompiler; `tests/TestCase.php` lists the providers in that order.

Tests boot the package through Orchestra Testbench: `tests/TestCase.php` runs the package migration on in-memory SQLite (with foreign keys on) and `tests/Pest.php` holds the bunny.net API fixtures. `tests/ArchTest.php` enforces `declare(strict_types=1)` in all `src` code and forbids `dd()`, `ddd()`, `env()`, and `exit()` anywhere; the package config therefore has no `env()` calls. PHPUnit runs in random order and fails on warnings, risky tests, and output.

`testbench.yaml` registers the package provider; Larastan boots the app from it, so view names resolve during analysis. The `workbench/` directory is a throwaway Laravel skeleton for manual testing via `composer serve`.

`resources/boost/skills/bunny-sidecar-development/SKILL.md` is a Laravel Boost skill shipped to consumers. Regenerate it with the `package-generate-skill` skill whenever the public API, commands, config, routes, or README change.

## Compatibility

Support matrix enforced by `.github/workflows/tests.yml`: PHP 8.3–8.5, Laravel 12 and 13 (Testbench 10 and 11 respectively), prefer-lowest and prefer-stable, on Ubuntu and Windows. Windows runs `vendor/bin/pest` without parallel or type coverage. Any new dependency or language feature must work across that whole matrix.

## Local Skills

- `package-scaffold`: use when adding package capabilities or wiring them through the service provider, including commands, migrations, routes, config, views, translations, assets, middleware, publish tags, workbench files, and console-only behavior.
- `package-testing`: use when adding or changing package tests with Pest 4/5 and Orchestra Testbench.
- `package-release`: use when preparing changelog, release notes, tags, or GitHub release workflow changes.
- `package-compatibility`: use when reviewing code, dependencies, or CI against the PHP and Laravel support matrix.
- `package-generate-skill`: use when updating the bundled Boost skill from the package implementation, README, and examples.
