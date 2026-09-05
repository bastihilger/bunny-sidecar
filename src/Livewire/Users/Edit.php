<?php

declare(strict_types=1);

namespace BunnySidecar\BunnySidecar\Livewire\Users;

use BunnySidecar\BunnySidecar\Enums\UserRole;
use BunnySidecar\BunnySidecar\Livewire\ZoneGrantsPage;
use BunnySidecar\BunnySidecar\Models\User;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;

#[Layout('bunny-sidecar::layouts.app')]
class Edit extends ZoneGrantsPage
{
    public User $user;

    public string $role = UserRole::Member->value;

    public function mount(User $user): void
    {
        abort_unless($this->currentUser()->isAdmin(), 403);

        $this->user = $user;
        $this->role = $user->role->value;
        $this->grantsForm->fillFromGrants($user->zoneGrants()->get());
    }

    public function save(): void
    {
        abort_unless($this->currentUser()->isAdmin(), 403);

        $this->validate(['role' => ['required', Rule::enum(UserRole::class)]]);

        $role = UserRole::from($this->role);

        if ($this->user->isAdmin() && $role !== UserRole::Admin && $this->isLastAdmin()) {
            Flux::toast(variant: 'danger', text: __('The last admin cannot be demoted.'));

            return;
        }

        $grantData = [];

        if ($role === UserRole::Member) {
            $this->grantsForm->validate();
            $grantData = $this->grantsForm->toGrantData();
        }

        DB::transaction(function () use ($role, $grantData): void {
            $this->user->update(['role' => $role]);
            $this->user->zoneGrants()->delete();

            foreach ($grantData as $grant) {
                $this->user->zoneGrants()->create($grant);
            }
        });

        Flux::toast(variant: 'success', text: __('User updated.'));

        $this->redirectRoute('bunny-sidecar.users.index', navigate: true);
    }

    public function render(): View
    {
        return view('bunny-sidecar::livewire.users.edit', [
            'zoneOptions' => UserRole::from($this->role) === UserRole::Member ? $this->zoneOptions() : [],
        ])->title(__('Edit :name', ['name' => $this->user->name]));
    }

    protected function isLastAdmin(): bool
    {
        return User::query()->where('role', UserRole::Admin)->count() <= 1;
    }
}
