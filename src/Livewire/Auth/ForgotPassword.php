<?php

declare(strict_types=1);

namespace BunnySidecar\BunnySidecar\Livewire\Auth;

use BunnySidecar\BunnySidecar\BunnySidecar;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Password;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('bunny-sidecar::layouts.auth')]
#[Title('Forgot password')]
class ForgotPassword extends Component
{
    public string $email = '';

    public function sendPasswordResetLink(BunnySidecar $sidecar): void
    {
        $this->validate(['email' => ['required', 'string', 'email']]);

        Password::broker($sidecar->guard())->sendResetLink(['email' => $this->email]);

        // Always the same answer, so the form does not reveal which emails exist.
        session()->flash('status', __('A reset link will be sent if the account exists.'));
    }

    public function render(): View
    {
        return view('bunny-sidecar::livewire.auth.forgot-password');
    }
}
