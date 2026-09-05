<?php

declare(strict_types=1);

namespace BunnySidecar\BunnySidecar\Livewire\Settings;

use BunnySidecar\BunnySidecar\Livewire\Concerns\ResolvesCurrentUser;
use BunnySidecar\BunnySidecar\Models\User;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('bunny-sidecar::layouts.app')]
#[Title('Profile settings')]
class Profile extends Component
{
    use ResolvesCurrentUser;

    public string $name = '';

    public string $email = '';

    public function mount(): void
    {
        $user = $this->currentUser();

        $this->name = $user->name;
        $this->email = $user->email;
    }

    public function updateProfileInformation(): void
    {
        $user = $this->currentUser();

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class, 'email')->ignore($user->id)],
        ]);

        $user->update($validated);

        Flux::toast(variant: 'success', text: __('Profile updated.'));
    }

    public function render(): View
    {
        return view('bunny-sidecar::livewire.settings.profile');
    }
}
