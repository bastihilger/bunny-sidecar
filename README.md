<div align="center">
    <h1>Bunny Sidecar</h1>
</div>

<p align="center">
    <a href="https://packagist.org/packages/bastihilger/bunny-sidecar"><img src="https://img.shields.io/packagist/v/bastihilger/bunny-sidecar.svg?style=flat-square" alt="Packagist"></a>
    <a href="https://packagist.org/packages/bastihilger/bunny-sidecar"><img src="https://img.shields.io/packagist/php-v/bastihilger/bunny-sidecar.svg?style=flat-square" alt="PHP from Packagist"></a>
    <a href="https://packagist.org/packages/bastihilger/bunny-sidecar"><img src="https://badge.laravel.cloud/badge/bastihilger/bunny-sidecar?style=flat" alt="Laravel versions"></a>
    <a href="https://github.com/bastihilger/bunny-sidecar/actions"><img alt="GitHub Workflow Status (main)" src="https://img.shields.io/github/actions/workflow/status/bastihilger/bunny-sidecar/tests.yml?branch=main&label=Tests&style=flat-square"></a>
    <a href="https://packagist.org/packages/bastihilger/bunny-sidecar"><img src="https://img.shields.io/packagist/dt/bastihilger/bunny-sidecar.svg?style=flat-square" alt="Total Downloads"></a>
</p>

An admin panel providing some missing bunny.net features

## Installation

You can install the package via Composer:

```bash
composer require bastihilger/bunny-sidecar
```

You may publish all of the package's resources at once:

```bash
php artisan vendor:publish --tag="bunny-sidecar"
```

Or, you may publish each resource individually:

### Publishing the Configuration File

```bash
php artisan vendor:publish --tag="bunny-sidecar-config"
```

### Publishing and Running the Migrations

```bash
php artisan vendor:publish --tag="bunny-sidecar-migrations"
php artisan migrate
```

### Publishing the Views

```bash
php artisan vendor:publish --tag="bunny-sidecar-views"
```

### Publishing the Translations

```bash
php artisan vendor:publish --tag="bunny-sidecar-lang"
```

### Publishing the Public Assets

```bash
php artisan vendor:publish --tag="bunny-sidecar-assets"
```

## Usage

<!-- Add a basic usage example here. -->

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
