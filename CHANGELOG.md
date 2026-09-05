# Release Notes

## [Unreleased](https://github.com/bastihilger/bunny-sidecar/compare/v0.1.0...1.x)

### Added

- Self-contained panel under a configurable prefix (`/bunny`) with its own users table, session guard, and password broker, so panel members never gain access to the host application.
- `bunny-sidecar:install` and `bunny-sidecar:create-admin` Artisan commands.
- Login, password reset, and invitation acceptance pages.
- Optional two-factor authentication with authenticator apps (TOTP) and single-use recovery codes.
- bunny.net account management with encrypted API keys, live DNS zone and record management, per-scope record type grants for members, own-record management, and per-record manager assignments.
- Shield (WAF) event search with the matched request data bunny.net hides.
- Precompiled stylesheet served by the package; free Flux UI only.

## [v0.1.0](https://github.com/bastihilger/bunny-sidecar/compare/...v0.1.0) - 202x-xx-xx

Initial pre-release.
