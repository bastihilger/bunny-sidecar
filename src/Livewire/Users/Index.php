<?php

declare(strict_types=1);

namespace BunnySidecar\BunnySidecar\Livewire\Users;

use BunnySidecar\BunnySidecar\Enums\UserRole;
use BunnySidecar\BunnySidecar\Livewire\Concerns\ResolvesCurrentUser;
use BunnySidecar\BunnySidecar\Mail\InvitationMailer;
use BunnySidecar\BunnySidecar\Models\Invitation;
use BunnySidecar\BunnySidecar\Models\User;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('bunny-sidecar::layouts.app')]
#[Title('Users')]
class Index extends Component
{
    use ResolvesCurrentUser;

    public ?int $pendingDeleteId = null;

    public function mount(): void
    {
        $this->authorizeAdmin();
    }

    public function confirmDelete(User $user): void
    {
        $this->authorizeAdmin();
        abort_if(! $this->canDelete($user), 403);

        $this->pendingDeleteId = $user->id;
        Flux::modal('delete-user')->show();
    }

    public function deleteUser(): void
    {
        $this->authorizeAdmin();

        $user = User::query()->findOrFail($this->pendingDeleteId);
        abort_if(! $this->canDelete($user), 403);

        $user->delete();
        $this->pendingDeleteId = null;

        Flux::modal('delete-user')->close();
        Flux::toast(variant: 'success', text: __('User deleted.'));
    }

    public function resendInvitation(Invitation $invitation, InvitationMailer $mailer): void
    {
        $this->authorizeAdmin();
        abort_if($invitation->accepted_at !== null, 404);

        $plainToken = $invitation->reissue();
        $mailer->send($invitation, $plainToken);

        Flux::toast(variant: 'success', text: __('Invitation sent again to :email.', ['email' => $invitation->email]));
    }

    public function revokeInvitation(Invitation $invitation): void
    {
        $this->authorizeAdmin();
        abort_if($invitation->accepted_at !== null, 404);

        $invitation->delete();

        Flux::toast(variant: 'success', text: __('Invitation revoked.'));
    }

    public function render(): View
    {
        return view('bunny-sidecar::livewire.users.index', [
            'users' => User::query()
                ->withCount('zoneGrants')
                ->orderBy('name')
                ->get(),
            'invitations' => Invitation::query()
                ->whereNull('accepted_at')
                ->with('invitedBy')
                ->orderByDesc('created_at')
                ->get(),
        ]);
    }

    protected function canDelete(User $user): bool
    {
        if ($user->is($this->currentUser())) {
            return false;
        }

        if ($user->isAdmin() && User::query()->where('role', UserRole::Admin)->count() <= 1) {
            return false;
        }

        return true;
    }

    protected function authorizeAdmin(): void
    {
        abort_unless($this->currentUser()->isAdmin(), 403);
    }
}
