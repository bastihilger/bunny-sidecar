<?php

declare(strict_types=1);

namespace BunnySidecar\BunnySidecar\Livewire\Users;

use BunnySidecar\BunnySidecar\Enums\UserRole;
use BunnySidecar\BunnySidecar\Livewire\ZoneGrantsPage;
use BunnySidecar\BunnySidecar\Mail\InvitationMailer;
use BunnySidecar\BunnySidecar\Models\Invitation;
use BunnySidecar\BunnySidecar\Models\User;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

#[Layout('bunny-sidecar::layouts.app')]
#[Title('Invite user')]
class Invite extends ZoneGrantsPage
{
    public string $email = '';

    public string $name = '';

    public string $role = UserRole::Member->value;

    public function mount(): void
    {
        abort_unless($this->currentUser()->isAdmin(), 403);
    }

    public function invite(InvitationMailer $mailer): void
    {
        abort_unless($this->currentUser()->isAdmin(), 403);

        $this->validate([
            'email' => [
                'required', 'string', 'email', 'max:255',
                Rule::unique(User::class, 'email'),
                Rule::unique(Invitation::class, 'email')->whereNull('accepted_at'),
            ],
            'name' => ['nullable', 'string', 'max:255'],
            'role' => ['required', Rule::enum(UserRole::class)],
        ], attributes: ['email' => __('email address')]);

        $role = UserRole::from($this->role);

        $zoneGrants = [];

        if ($role === UserRole::Member) {
            $this->grantsForm->validate();
            $zoneGrants = $this->grantsForm->toGrantData();
        }

        [$invitation, $plainToken] = Invitation::issue(
            email: $this->email,
            name: $this->name !== '' ? $this->name : null,
            role: $role,
            zoneGrants: $zoneGrants,
            invitedBy: $this->currentUser(),
        );

        $mailer->send($invitation, $plainToken);

        Flux::toast(variant: 'success', text: __('Invitation sent to :email.', ['email' => $invitation->email]));

        $this->redirectRoute('bunny-sidecar.users.index', navigate: true);
    }

    public function render(): View
    {
        return view('bunny-sidecar::livewire.users.invite', [
            'zoneOptions' => UserRole::from($this->role) === UserRole::Member ? $this->zoneOptions() : [],
        ]);
    }
}
