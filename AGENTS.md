# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

`CLAUDE.md` is a symlink to `AGENTS.md`, and `.claude/` is a symlink to `.agents/`. Edit `AGENTS.md` and `.agents/` directly; both names resolve to the same files.

# Bunny Sidecar

This repository is a Laravel package (`bastihilger/bunny-sidecar`, namespace `BunnySidecar\BunnySidecar`). It is currently a scaffold: the `BunnySidecar` class, command, config key, route, view, translation, and migration are all placeholders waiting for real bunny.net features. Keep the package focused, idiomatic, and easy for Laravel developers to install, test, and maintain.

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
- Workbench build / dev server: `composer build` / `composer serve`
- Re-run package discovery after changing the provider or `composer.json` extra: `composer prepare` (also runs on every autoload dump)

## Architecture

Everything the host app sees is wired in `src/BunnySidecarServiceProvider.php`:

- `register()` merges `config/bunny-sidecar.php` under the `bunny-sidecar` key and binds `BunnySidecar` as a singleton (exposed via the `BunnySidecar` facade).
- `boot()` loads `routes/bunny-sidecar.php`, views under the `bunny-sidecar::` namespace, and translations under `bunny-sidecar::`. Console-only work (publishing and command registration) sits behind `runningInConsole()`.
- Publish tags follow `bunny-sidecar-{config,views,lang,assets,migrations}`, with the umbrella tag `bunny-sidecar`. The README documents each tag; keep them in sync when adding a resource.
- Artisan commands live in `src/Console/Commands` and use the `bunny-sidecar:` prefix.

Tests boot the package through Orchestra Testbench: `tests/TestCase.php` registers only `BunnySidecarServiceProvider`, and `tests/Pest.php` applies it to every test. `tests/ArchTest.php` enforces `declare(strict_types=1)` in all `src` code and forbids `dd()`, `ddd()`, `env()`, and `exit()` anywhere. PHPUnit runs in random order and fails on warnings, risky tests, and output, so keep tests clean.

The `workbench/` directory is a throwaway Laravel skeleton for manual testing via `composer serve`; it is autoloaded under `Workbench\` and configured by `testbench.yaml`. It is not part of the shipped package.

`resources/boost/skills/bunny-sidecar-development/SKILL.md` is a Laravel Boost skill shipped to consumers. Regenerate it with the `package-generate-skill` skill whenever the public API, commands, config, routes, or README change.

## Compatibility

Support matrix enforced by `.github/workflows/tests.yml`: PHP 8.3–8.5, Laravel 12 and 13 (Testbench 10 and 11 respectively), prefer-lowest and prefer-stable, on Ubuntu and Windows. Windows runs `vendor/bin/pest` without parallel or type coverage. Any new dependency or language feature must work across that whole matrix.

## Local Skills

- `package-scaffold`: use when adding package capabilities or wiring them through the service provider, including commands, migrations, routes, config, views, translations, assets, middleware, publish tags, workbench files, and console-only behavior.
- `package-testing`: use when adding or changing package tests with Pest 4/5 and Orchestra Testbench.
- `package-release`: use when preparing changelog, release notes, tags, or GitHub release workflow changes.
- `package-compatibility`: use when reviewing code, dependencies, or CI against the PHP and Laravel support matrix.
- `package-generate-skill`: use when updating the bundled Boost skill from the package implementation, README, and examples.
