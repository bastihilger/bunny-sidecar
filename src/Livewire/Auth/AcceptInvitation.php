<?php

declare(strict_types=1);

namespace BunnySidecar\BunnySidecar\Livewire\Auth;

use BunnySidecar\BunnySidecar\BunnySidecar;
use BunnySidecar\BunnySidecar\Concerns\PasswordValidationRules;
use BunnySidecar\BunnySidecar\Enums\UserRole;
use BunnySidecar\BunnySidecar\Models\BunnyAccount;
use BunnySidecar\BunnySidecar\Models\Invitation;
use BunnySidecar\BunnySidecar\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('bunny-sidecar::layouts.auth')]
#[Title('Accept invitation')]
class AcceptInvitation extends Component
{
    use PasswordValidationRules;

    #[Locked]
    public string $token = '';

    public string $name = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function mount(string $token): void
    {
        $this->token = $token;

        $invitation = $this->invitation();

        if ($invitation !== null) {
            $this->name = $invitation->name ?? '';
        }
    }

    public function accept(BunnySidecar $sidecar): void
    {
        $invitation = $this->invitation();
        abort_if($invitation === null, 403);

        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'password' => $this->passwordRules(),
        ]);

        $user = DB::transaction(function () use ($invitation): User {
            $user = User::query()->create([
                'name' => $this->name,
                'email' => $invitation->email,
                'password' => $this->password,
                'role' => $invitation->role,
            ]);

            if ($invitation->role === UserRole::Member) {
                foreach ($invitation->zone_grants ?? [] as $grant) {
                    if (! BunnyAccount::query()->whereKey($grant['bunny_account_id'])->exists()) {
                        continue;
                    }

                    $user->zoneGrants()->create($grant);
                }
            }

            $invitation->update(['accepted_at' => now()]);

            return $user;
        });

        $sidecar->auth()->login($user);
        session()->regenerate();

        $this->redirectRoute('bunny-sidecar.zones.index', navigate: true);
    }

    public function render(): View
    {
        return view('bunny-sidecar::livewire.auth.accept-invitation', ['invitation' => $this->invitation()]);
    }

    protected function invitation(): ?Invitation
    {
        $invitation = Invitation::findByPlainToken($this->token);

        return $invitation?->isPending() === true ? $invitation : null;
    }
}
