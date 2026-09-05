<?php

declare(strict_types=1);

namespace BunnySidecar\BunnySidecar\Livewire\Dns;

use BunnySidecar\BunnySidecar\Access\RecordOwnerships;
use BunnySidecar\BunnySidecar\Access\ZoneAccess;
use BunnySidecar\BunnySidecar\Bunny\BunnyApiException;
use BunnySidecar\BunnySidecar\Bunny\BunnyClient;
use BunnySidecar\BunnySidecar\Bunny\Dtos\DnsRecord;
use BunnySidecar\BunnySidecar\Bunny\ZoneDirectory;
use BunnySidecar\BunnySidecar\Enums\DnsEntryType;
use BunnySidecar\BunnySidecar\Enums\DnsRecordScope;
use BunnySidecar\BunnySidecar\Livewire\Concerns\ResolvesCurrentUser;
use BunnySidecar\BunnySidecar\Livewire\Forms\DnsRecordForm;
use BunnySidecar\BunnySidecar\Models\BunnyAccount;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * The add/edit modal for one DNS record, nested in the zone page.
 */
class RecordForm extends Component
{
    use ResolvesCurrentUser;

    public BunnyAccount $account;

    public string $domain;

    public int $zoneId;

    public DnsRecordForm $form;

    #[On('open-record-form')]
    public function open(?int $recordId = null): void
    {
        if ($recordId !== null) {
            $record = $this->findRecord($recordId);
            abort_if($record === null, 404);

            abort_unless($this->access()->canManageRecord($this->currentUser(), $this->account, $this->domain, $record), 403);

            $this->form->fillFromRecord($record);
        } else {
            $addableTypes = $this->addableTypes();
            abort_if($addableTypes === [], 403);

            $this->form->resetForType($addableTypes[0]);
        }

        $this->resetValidation();
        Flux::modal('dns-record-form')->show();
    }

    public function save(ZoneDirectory $directory, RecordOwnerships $ownerships): void
    {
        $user = $this->currentUser();
        $access = $this->access();
        $isUpdate = $this->form->recordId !== null;

        $type = $this->form->entryType();
        $scope = DnsRecordScope::fromName($this->form->name);

        if ($this->form->recordId !== null) {
            $record = $this->findRecord($this->form->recordId);
            abort_if($record === null, 404);

            abort_unless($access->canManageRecord($user, $this->account, $this->domain, $record), 403);

            // Moving a record between the zone root and a subdomain is the same as creating it there.
            $needsScopeCheck = $record->scope() !== $scope;
        } else {
            abort_unless(in_array($type, $this->addableTypes(), true), 403);

            $needsScopeCheck = true;
        }

        $this->form->validate();

        if ($needsScopeCheck && ! $access->canAddRecord($user, $this->account, $this->domain, $type, $scope)) {
            $this->addError('form.name', $scope === DnsRecordScope::Root
                ? __('You may not place :type records at the zone root.', ['type' => $type->label()])
                : __('You may not place :type records on subdomains.', ['type' => $type->label()]));

            return;
        }

        try {
            if ($this->form->recordId !== null) {
                BunnyClient::for($this->account)->updateRecord($this->zoneId, $this->form->recordId, $this->form->toPayload());
            } else {
                $created = BunnyClient::for($this->account)->createRecord($this->zoneId, $this->form->toPayload());

                $ownerships->claim($user, $this->account, $this->domain, $created->id);
            }
        } catch (BunnyApiException $exception) {
            Flux::toast(variant: 'danger', text: $exception->userMessage());

            return;
        }

        $directory->forgetRecords($this->account, $this->zoneId);
        $this->form->reset();

        Flux::modal('dns-record-form')->close();
        Flux::toast(variant: 'success', text: $isUpdate ? __('DNS record updated.') : __('DNS record added.'));

        $this->dispatch('record-saved');
    }

    public function render(): View
    {
        return view('bunny-sidecar::livewire.dns.record-form', [
            'entryType' => $this->form->entryType(),
            'typeOptions' => $this->typeOptions(),
            'isUpdate' => $this->form->recordId !== null,
        ]);
    }

    /**
     * The record types the current user may add somewhere in this zone.
     *
     * @return list<DnsEntryType>
     */
    protected function addableTypes(): array
    {
        $user = $this->currentUser();
        $access = $this->access();

        return array_values(array_filter(
            DnsEntryType::cases(),
            fn (DnsEntryType $type): bool => collect(DnsRecordScope::cases())->contains(
                fn (DnsRecordScope $scope): bool => $access->canAddRecord($user, $this->account, $this->domain, $type, $scope),
            ),
        ));
    }

    /**
     * @return array<int, string>
     */
    protected function typeOptions(): array
    {
        if ($this->form->recordId !== null) {
            return [$this->form->type => $this->form->entryType()->label()];
        }

        $options = [];

        foreach ($this->addableTypes() as $type) {
            $options[$type->value] = $type->label();
        }

        return $options;
    }

    protected function findRecord(int $recordId): ?DnsRecord
    {
        try {
            $records = app(ZoneDirectory::class)->zoneWithRecords($this->account, $this->zoneId)->records;
        } catch (BunnyApiException) {
            return null;
        }

        return collect($records)->first(fn (DnsRecord $record): bool => $record->id === $recordId);
    }

    protected function access(): ZoneAccess
    {
        return app(ZoneAccess::class);
    }
}
