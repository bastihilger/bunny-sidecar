<?php

declare(strict_types=1);

namespace BunnySidecar\BunnySidecar;

use BunnySidecar\BunnySidecar\Console\Commands\BunnySidecarCommand;
use Illuminate\Support\ServiceProvider;

class BunnySidecarServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/bunny-sidecar.php', 'bunny-sidecar');

        $this->app->singleton(BunnySidecar::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../routes/bunny-sidecar.php');

        $this->loadViewsFrom(__DIR__.'/../resources/views', 'bunny-sidecar');

        $this->loadTranslationsFrom(__DIR__.'/../lang', 'bunny-sidecar');

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/bunny-sidecar.php' => config_path('bunny-sidecar.php'),
        ], ['bunny-sidecar', 'bunny-sidecar-config']);

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/bunny-sidecar'),
        ], ['bunny-sidecar', 'bunny-sidecar-views']);

        $this->publishes([
            __DIR__.'/../lang' => $this->app->langPath('vendor/bunny-sidecar'),
        ], ['bunny-sidecar', 'bunny-sidecar-lang']);

        $this->publishes([
            __DIR__.'/../public' => public_path('vendor/bunny-sidecar'),
        ], ['bunny-sidecar', 'bunny-sidecar-assets']);

        $this->publishesMigrations([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], ['bunny-sidecar', 'bunny-sidecar-migrations']);

        $this->commands([
            BunnySidecarCommand::class,
        ]);
    }
}
