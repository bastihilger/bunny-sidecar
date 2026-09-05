<?php

declare(strict_types=1);

use BunnySidecar\BunnySidecar\BunnySidecar;
use BunnySidecar\BunnySidecar\Http\Controllers\HomeController;
use BunnySidecar\BunnySidecar\Http\Controllers\LogoutController;
use BunnySidecar\BunnySidecar\Http\Controllers\StylesheetController;
use BunnySidecar\BunnySidecar\Http\Middleware\Authenticate;
use BunnySidecar\BunnySidecar\Http\Middleware\EnsureUserIsAdmin;
use BunnySidecar\BunnySidecar\Http\Middleware\RedirectIfAuthenticated;
use BunnySidecar\BunnySidecar\Livewire\Accounts\Index as AccountsIndex;
use BunnySidecar\BunnySidecar\Livewire\Auth\AcceptInvitation;
use BunnySidecar\BunnySidecar\Livewire\Auth\ForgotPassword;
use BunnySidecar\BunnySidecar\Livewire\Auth\Login;
use BunnySidecar\BunnySidecar\Livewire\Auth\ResetPassword;
use BunnySidecar\BunnySidecar\Livewire\Settings\Password as SettingsPassword;
use BunnySidecar\BunnySidecar\Livewire\Settings\Profile as SettingsProfile;
use BunnySidecar\BunnySidecar\Livewire\Shield\Events as ShieldEvents;
use BunnySidecar\BunnySidecar\Livewire\Users\Edit as UsersEdit;
use BunnySidecar\BunnySidecar\Livewire\Users\Index as UsersIndex;
use BunnySidecar\BunnySidecar\Livewire\Users\Invite as UsersInvite;
use BunnySidecar\BunnySidecar\Livewire\Zones\Index as ZonesIndex;
use BunnySidecar\BunnySidecar\Livewire\Zones\Show as ZonesShow;
use Illuminate\Support\Facades\Route;

Route::get('bunny-sidecar/assets/bunny-sidecar.css', StylesheetController::class)
    ->name('bunny-sidecar.assets.css');

Route::group(app(BunnySidecar::class)->routeGroupAttributes(), function (): void {
    Route::get('/', HomeController::class)->name('home');

    Route::middleware(RedirectIfAuthenticated::class)->group(function (): void {
        Route::get('login', Login::class)->name('login');
        Route::get('password/request', ForgotPassword::class)->name('password.request');
        Route::get('password/reset/{token}', ResetPassword::class)->name('password.reset');
        Route::get('invitation/{token}', AcceptInvitation::class)->name('invitations.accept');
    });

    Route::middleware(Authenticate::class)->group(function (): void {
        Route::post('logout', LogoutController::class)->name('logout');

        Route::get('zones', ZonesIndex::class)->name('zones.index');
        Route::get('zones/{account}/{domain}', ZonesShow::class)
            ->where('domain', '[a-zA-Z0-9.\-]+')
            ->name('zones.show');

        Route::get('settings/profile', SettingsProfile::class)->name('profile.edit');
        Route::get('settings/password', SettingsPassword::class)->name('password.edit');

        Route::middleware(EnsureUserIsAdmin::class)->group(function (): void {
            Route::get('accounts', AccountsIndex::class)->name('accounts.index');
            Route::get('shield/events', ShieldEvents::class)->name('shield.events');
            Route::get('users', UsersIndex::class)->name('users.index');
            Route::get('users/invite', UsersInvite::class)->name('users.invite');
            Route::get('users/{user}/edit', UsersEdit::class)->name('users.edit');
        });
    });
});
