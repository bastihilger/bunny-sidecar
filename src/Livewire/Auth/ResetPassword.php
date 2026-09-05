<?php

declare(strict_types=1);

namespace BunnySidecar\BunnySidecar\Livewire\Auth;

use BunnySidecar\BunnySidecar\BunnySidecar;
use BunnySidecar\BunnySidecar\Concerns\PasswordValidationRules;
use BunnySidecar\BunnySidecar\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('bunny-sidecar::layouts.auth')]
#[Title('Reset password')]
class ResetPassword extends Component
{
    use PasswordValidationRules;

    #[Locked]
    public string $token = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function mount(string $token): void
    {
        $this->token = $token;
        $this->email = request()->string('email')->toString();
    }

    public function resetPassword(BunnySidecar $sidecar): void
    {
        $this->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email'],
            'password' => $this->passwordRules(),
        ]);

        $status = Password::broker($sidecar->guard())->reset(
            [
                'email' => $this->email,
                'password' => $this->password,
                'password_confirmation' => $this->password_confirmation,
                'token' => $this->token,
            ],
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            $this->addError('email', __($status));

            return;
        }

        session()->flash('status', __($status));

        $this->redirectRoute('bunny-sidecar.login', navigate: true);
    }

    public function render(): View
    {
        return view('bunny-sidecar::livewire.auth.reset-password');
    }
}
