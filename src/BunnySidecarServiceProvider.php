<?php

declare(strict_types=1);

namespace BunnySidecar\BunnySidecar;

use BunnySidecar\BunnySidecar\Access\RecordManagers;
use BunnySidecar\BunnySidecar\Access\RecordOwnerships;
use BunnySidecar\BunnySidecar\Access\ZoneAccess;
use BunnySidecar\BunnySidecar\Console\Commands\CreateAdminCommand;
use BunnySidecar\BunnySidecar\Console\Commands\InstallCommand;
use BunnySidecar\BunnySidecar\Http\Middleware\Authenticate;
use BunnySidecar\BunnySidecar\Http\Middleware\EnsureUserIsAdmin;
use BunnySidecar\BunnySidecar\Http\Middleware\RedirectIfAuthenticated;
use BunnySidecar\BunnySidecar\Livewire\Accounts\Index as AccountsIndex;
use BunnySidecar\BunnySidecar\Livewire\Auth\AcceptInvitation;
use BunnySidecar\BunnySidecar\Livewire\Auth\ForgotPassword;
use BunnySidecar\BunnySidecar\Livewire\Auth\Login;
use BunnySidecar\BunnySidecar\Livewire\Auth\ResetPassword;
use BunnySidecar\BunnySidecar\Livewire\Dns\RecordForm;
use BunnySidecar\BunnySidecar\Livewire\Settings\Password as SettingsPassword;
use BunnySidecar\BunnySidecar\Livewire\Settings\Profile as SettingsProfile;
use BunnySidecar\BunnySidecar\Livewire\Shield\Events as ShieldEvents;
use BunnySidecar\BunnySidecar\Livewire\Users\Edit as UsersEdit;
use BunnySidecar\BunnySidecar\Livewire\Users\Index as UsersIndex;
use BunnySidecar\BunnySidecar\Livewire\Users\Invite as UsersInvite;
use BunnySidecar\BunnySidecar\Livewire\Zones\Index as ZonesIndex;
use BunnySidecar\BunnySidecar\Livewire\Zones\Show as ZonesShow;
use BunnySidecar\BunnySidecar\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\View as ViewFactory;
use Illuminate\Support\ServiceProvider;
use Livewire\Component;
use Livewire\Livewire;

class BunnySidecarServiceProvider extends ServiceProvider
{
    /**
     * Livewire component aliases, used for nested tags and in tests.
     *
     * @var array<string, class-string<Component>>
     */
    public const array COMPONENTS = [
        'bunny-sidecar.auth.login' => Login::class,
        'bunny-sidecar.auth.forgot-password' => ForgotPassword::class,
        'bunny-sidecar.auth.reset-password' => ResetPassword::class,
        'bunny-sidecar.auth.accept-invitation' => AcceptInvitation::class,
        'bunny-sidecar.zones.index' => ZonesIndex::class,
        'bunny-sidecar.zones.show' => ZonesShow::class,
        'bunny-sidecar.dns.record-form' => RecordForm::class,
        'bunny-sidecar.accounts.index' => AccountsIndex::class,
        'bunny-sidecar.shield.events' => ShieldEvents::class,
        'bunny-sidecar.users.index' => UsersIndex::class,
        'bunny-sidecar.users.invite' => UsersInvite::class,
        'bunny-sidecar.users.edit' => UsersEdit::class,
        'bunny-sidecar.settings.profile' => SettingsProfile::class,
        'bunny-sidecar.settings.password' => SettingsPassword::class,
    ];

    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/bunny-sidecar.php', 'bunny-sidecar');

        $this->app->singleton(BunnySidecar::class);
        $this->app->scoped(ZoneAccess::class);
        $this->app->scoped(RecordOwnerships::class);
        $this->app->scoped(RecordManagers::class);

        $this->registerAuthGuard();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'bunny-sidecar');
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'bunny-sidecar');
        $this->loadJsonTranslationsFrom(__DIR__.'/../lang');
        $this->loadRoutesFrom(__DIR__.'/../routes/bunny-sidecar.php');

        $this->registerLivewire();

        ViewFactory::composer('bunny-sidecar::layouts.app', function (View $view): void {
            $view->with('user', $this->app->make(BunnySidecar::class)->currentUser());
        });

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

        $this->publishesMigrations([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], ['bunny-sidecar', 'bunny-sidecar-migrations']);

        $this->commands([
            InstallCommand::class,
            CreateAdminCommand::class,
        ]);
    }

    /**
     * Panel users authenticate through their own session guard backed by the
     * package's user model, so they never gain access to the host app. The
     * host may predefine any of these entries to override them.
     */
    protected function registerAuthGuard(): void
    {
        $config = config();
        $guard = $config->string('bunny-sidecar.guard');

        if (! $config->has("auth.guards.{$guard}")) {
            $config->set("auth.guards.{$guard}", ['driver' => 'session', 'provider' => $guard]);
        }

        if (! $config->has("auth.providers.{$guard}")) {
            $config->set("auth.providers.{$guard}", ['driver' => 'eloquent', 'model' => User::class]);
        }

        if (! $config->has("auth.passwords.{$guard}")) {
            $config->set("auth.passwords.{$guard}", [
                'provider' => $guard,
                'table' => 'bunny_sidecar_password_reset_tokens',
                'expire' => $config->integer('bunny-sidecar.password_reset.expire', 60),
                'throttle' => $config->integer('bunny-sidecar.password_reset.throttle', 60),
            ]);
        }
    }

    protected function registerLivewire(): void
    {
        foreach (self::COMPONENTS as $alias => $class) {
            Livewire::component($alias, $class);
        }

        // Re-apply the panel's auth middleware on Livewire update requests.
        Livewire::addPersistentMiddleware([
            Authenticate::class,
            EnsureUserIsAdmin::class,
            RedirectIfAuthenticated::class,
        ]);
    }
}
