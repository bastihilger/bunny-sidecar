<?php

declare(strict_types=1);

namespace BunnySidecar\BunnySidecar\Livewire\Auth;

use BunnySidecar\BunnySidecar\BunnySidecar;
use Illuminate\Contracts\View\View;
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

    public function login(BunnySidecar $sidecar): void
    {
        $this->validate();

        if (RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            throw ValidationException::withMessages([
                'email' => __('auth.throttle', [
                    'seconds' => RateLimiter::availableIn($this->throttleKey()),
                ]),
            ]);
        }

        if (! $sidecar->auth()->attempt(['email' => $this->email, 'password' => $this->password], $this->remember)) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        RateLimiter::clear($this->throttleKey());
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
