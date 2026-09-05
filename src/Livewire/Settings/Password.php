<?php

declare(strict_types=1);

namespace BunnySidecar\BunnySidecar\Livewire\Settings;

use BunnySidecar\BunnySidecar\BunnySidecar;
use BunnySidecar\BunnySidecar\Concerns\PasswordValidationRules;
use BunnySidecar\BunnySidecar\Livewire\Concerns\ResolvesCurrentUser;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('bunny-sidecar::layouts.app')]
#[Title('Password settings')]
class Password extends Component
{
    use PasswordValidationRules, ResolvesCurrentUser;

    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function updatePassword(BunnySidecar $sidecar): void
    {
        $user = $this->currentUser();

        $validated = $this->validate([
            'current_password' => ['required', 'string', 'current_password:'.$sidecar->guard()],
            'password' => $this->passwordRules(),
        ]);

        $user->update(['password' => $validated['password']]);

        $this->reset('current_password', 'password', 'password_confirmation');

        Flux::toast(variant: 'success', text: __('Password updated.'));
    }

    public function render(): View
    {
        return view('bunny-sidecar::livewire.settings.password');
    }
}
