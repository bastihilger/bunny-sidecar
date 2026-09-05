<?php

declare(strict_types=1);

namespace BunnySidecar\BunnySidecar\Tests;

use BunnySidecar\BunnySidecar\BunnySidecar;
use BunnySidecar\BunnySidecar\BunnySidecarServiceProvider;
use BunnySidecar\BunnySidecar\Models\User;
use Flux\FluxServiceProvider;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->runPackageMigrations();
    }

    /**
     * @return list<class-string<ServiceProvider>>
     */
    protected function getPackageProviders($app): array
    {
        // Flux must boot before Livewire so its tag compiler runs ahead of
        // Livewire's wire:key precompiler, matching package discovery order.
        return [
            FluxServiceProvider::class,
            LivewireServiceProvider::class,
            BunnySidecarServiceProvider::class,
        ];
    }

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing.foreign_key_constraints', true);
        $app['config']->set('cache.default', 'array');
        $app['config']->set('session.driver', 'array');
        $app['config']->set('mail.default', 'array');
        $app['config']->set('queue.default', 'sync');
    }

    protected function runPackageMigrations(): void
    {
        foreach (glob(__DIR__.'/../database/migrations/*.php') ?: [] as $file) {
            $migration = require $file;

            if ($migration instanceof Migration) {
                $migration->up();
            }
        }
    }

    /**
     * Authenticate the given user on the package guard.
     */
    protected function actingAsPanelUser(User $user): static
    {
        return $this->actingAs($user, app(BunnySidecar::class)->guard());
    }
}
