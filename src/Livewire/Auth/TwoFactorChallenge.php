<?php

declare(strict_types=1);

namespace BunnySidecar\BunnySidecar\Livewire\Auth;

use BunnySidecar\BunnySidecar\Auth\TwoFactorAuthenticator;
use BunnySidecar\BunnySidecar\BunnySidecar;
use BunnySidecar\BunnySidecar\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Second login step for users with an authenticator app. The password step
 * stores the user id in the session; this page turns it into a login.
 */
#[Layout('bunny-sidecar::layouts.auth')]
#[Title('Two-factor authentication')]
class TwoFactorChallenge extends Component
{
    public const string SESSION_USER = 'bunny-sidecar.login.id';

    public const string SESSION_REMEMBER = 'bunny-sidecar.login.remember';

    public string $code = '';

    public string $recovery_code = '';

    public function mount(): void
    {
        if (! session()->has(self::SESSION_USER)) {
            $this->redirectRoute('bunny-sidecar.login', navigate: true);
        }
    }

    public function authenticate(BunnySidecar $sidecar, TwoFactorAuthenticator $twoFactor): void
    {
        $user = $this->pendingUser();

        if ($user === null) {
            $this->redirectRoute('bunny-sidecar.login', navigate: true);

            return;
        }

        $this->validate([
            'code' => ['required_without:recovery_code', 'nullable', 'string'],
            'recovery_code' => ['required_without:code', 'nullable', 'string'],
        ]);

        if (RateLimiter::tooManyAttempts($this->throttleKey($user), 5)) {
            throw ValidationException::withMessages([
                'code' => __('auth.throttle', ['seconds' => RateLimiter::availableIn($this->throttleKey($user))]),
            ]);
        }

        $valid = $this->code !== ''
            ? $twoFactor->verifyCode($user, $this->code)
            : $twoFactor->verifyRecoveryCode($user, $this->recovery_code);

        if (! $valid) {
            RateLimiter::hit($this->throttleKey($user));

            throw ValidationException::withMessages([
                $this->code !== '' ? 'code' : 'recovery_code' => __('The provided two-factor authentication code was invalid.'),
            ]);
        }

        RateLimiter::clear($this->throttleKey($user));

        $remember = (bool) session()->pull(self::SESSION_REMEMBER, false);
        session()->forget(self::SESSION_USER);

        $sidecar->auth()->login($user, $remember);
        session()->regenerate();

        $this->redirectIntended(default: $sidecar->homeUrl(), navigate: true);
    }

    public function render(): View
    {
        return view('bunny-sidecar::livewire.auth.two-factor-challenge');
    }

    protected function pendingUser(): ?User
    {
        $id = session()->get(self::SESSION_USER);

        return is_int($id) || is_string($id) ? User::query()->find($id) : null;
    }

    protected function throttleKey(User $user): string
    {
        return 'bunny-sidecar-two-factor|'.$user->id.'|'.request()->ip();
    }
}
