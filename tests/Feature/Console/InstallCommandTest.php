<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

afterEach(function (): void {
    File::delete(config_path('bunny-sidecar.php'));

    foreach (File::glob(database_path('migrations/*_create_bunny_sidecar_tables.php')) as $file) {
        File::delete($file);
    }
});

test('install publishes the config and migrations and prints the login url', function (): void {
    $this->artisan('bunny-sidecar:install', ['--no-migrate' => true, '--no-admin' => true])
        ->expectsOutputToContain(route('bunny-sidecar.login'))
        ->assertSuccessful();

    expect(File::exists(config_path('bunny-sidecar.php')))->toBeTrue()
        ->and(File::glob(database_path('migrations/*_create_bunny_sidecar_tables.php')))->toHaveCount(1);
});

test('install asks before migrating and creating the first admin', function (): void {
    $this->artisan('bunny-sidecar:install')
        ->expectsConfirmation('Run the database migrations now?', 'no')
        ->expectsConfirmation('Create the first admin user now?', 'no')
        ->assertSuccessful();
});
