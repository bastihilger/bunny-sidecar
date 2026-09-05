<?php

declare(strict_types=1);

namespace BunnySidecar\BunnySidecar\Livewire\Auth;

use BunnySidecar\BunnySidecar\Auth\TwoFactorAuthenticator;
use BunnySidecar\BunnySidecar\BunnySidecar;
use BunnySidecar\BunnySidecar\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('bunny-sidecar::layouts.auth')]
#[Title('Log in')]
class Login extends Component
{
    public string $email = '';

    public string $password = '';

    public bool $remember = false;

    /**
     * @return array<string, list<string>>
     */
    protected function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    public function login(BunnySidecar $sidecar, TwoFactorAuthenticator $twoFactor): void
    {
        $this->validate();

        if (RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            throw ValidationException::withMessages([
                'email' => __('auth.throttle', [
                    'seconds' => RateLimiter::availableIn($this->throttleKey()),
                ]),
            ]);
        }

        $credentials = ['email' => $this->email, 'password' => $this->password];
        $provider = Auth::createUserProvider($sidecar->guard());
        $user = $provider?->retrieveByCredentials($credentials);

        if ($provider === null || ! $user instanceof User || ! $provider->validateCredentials($user, $credentials)) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        RateLimiter::clear($this->throttleKey());

        if ($twoFactor->isRequiredFor($user)) {
            // Park the login until the authenticator code has been entered.
            session()->put([
                TwoFactorChallenge::SESSION_USER => $user->getKey(),
                TwoFactorChallenge::SESSION_REMEMBER => $this->remember,
            ]);

            $this->redirectRoute('bunny-sidecar.two-factor.login', navigate: true);

            return;
        }

        $sidecar->auth()->login($user, $this->remember);
        session()->regenerate();

        $this->redirectIntended(default: $sidecar->homeUrl(), navigate: true);
    }

    public function render(): View
    {
        return view('bunny-sidecar::livewire.auth.login');
    }

    protected function throttleKey(): string
    {
        return 'bunny-sidecar-login|'.Str::transliterate(Str::lower($this->email)).'|'.request()->ip();
    }
}
