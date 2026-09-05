<?php

declare(strict_types=1);

namespace BunnySidecar\BunnySidecar\Livewire\Accounts;

use BunnySidecar\BunnySidecar\Bunny\BunnyApiException;
use BunnySidecar\BunnySidecar\Bunny\BunnyClient;
use BunnySidecar\BunnySidecar\Bunny\ShieldDirectory;
use BunnySidecar\BunnySidecar\Bunny\ZoneDirectory;
use BunnySidecar\BunnySidecar\Livewire\Concerns\ResolvesCurrentUser;
use BunnySidecar\BunnySidecar\Livewire\Forms\BunnyAccountForm;
use BunnySidecar\BunnySidecar\Models\BunnyAccount;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('bunny-sidecar::layouts.app')]
#[Title('Bunny Accounts')]
class Index extends Component
{
    use ResolvesCurrentUser;

    public BunnyAccountForm $form;

    public ?int $pendingDeleteId = null;

    public function mount(): void
    {
        $this->authorizeAdmin();
    }

    public function create(): void
    {
        $this->authorizeAdmin();

        $this->form->reset();
        $this->resetValidation();

        Flux::modal('account-form')->show();
    }

    public function edit(BunnyAccount $account): void
    {
        $this->authorizeAdmin();

        $this->form->setAccount($account);
        $this->resetValidation();

        Flux::modal('account-form')->show();
    }

    public function save(ZoneDirectory $directory, ShieldDirectory $shieldDirectory): void
    {
        $this->authorizeAdmin();

        $account = $this->form->save();
        $directory->forgetZones($account);
        $shieldDirectory->forgetShieldZones($account);

        Flux::modal('account-form')->close();
        Flux::toast(variant: 'success', text: __('Bunny account saved.'));
    }

    public function testConnection(BunnyAccount $account): void
    {
        $this->authorizeAdmin();

        try {
            $count = BunnyClient::for($account)->zones()->count();
        } catch (BunnyApiException $exception) {
            Flux::toast(variant: 'danger', text: $exception->userMessage());

            return;
        }

        Flux::toast(variant: 'success', text: __(':count DNS zones found for ":account".', ['count' => $count, 'account' => $account->name]));
    }

    public function confirmDelete(BunnyAccount $account): void
    {
        $this->authorizeAdmin();

        $this->pendingDeleteId = $account->id;
        Flux::modal('delete-account')->show();
    }

    public function delete(ZoneDirectory $directory, ShieldDirectory $shieldDirectory): void
    {
        $this->authorizeAdmin();

        $account = BunnyAccount::query()->findOrFail($this->pendingDeleteId);

        $directory->forgetZones($account);
        $shieldDirectory->forgetShieldZones($account);
        $account->delete();
        $this->pendingDeleteId = null;

        Flux::modal('delete-account')->close();
        Flux::toast(variant: 'success', text: __('Bunny account deleted.'));
    }

    public function render(): View
    {
        return view('bunny-sidecar::livewire.accounts.index', [
            'accounts' => BunnyAccount::query()
                ->withCount('grants')
                ->orderBy('name')
                ->get(),
        ]);
    }

    protected function authorizeAdmin(): void
    {
        abort_unless($this->currentUser()->isAdmin(), 403);
    }
}
