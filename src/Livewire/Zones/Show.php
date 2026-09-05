<?php

declare(strict_types=1);

namespace BunnySidecar\BunnySidecar\Livewire\Zones;

use BunnySidecar\BunnySidecar\Access\RecordManagers;
use BunnySidecar\BunnySidecar\Access\RecordOwnerships;
use BunnySidecar\BunnySidecar\Access\ZoneAccess;
use BunnySidecar\BunnySidecar\Bunny\BunnyApiException;
use BunnySidecar\BunnySidecar\Bunny\BunnyClient;
use BunnySidecar\BunnySidecar\Bunny\Dtos\DnsRecord;
use BunnySidecar\BunnySidecar\Bunny\ZoneDirectory;
use BunnySidecar\BunnySidecar\Livewire\Concerns\ResolvesCurrentUser;
use BunnySidecar\BunnySidecar\Models\BunnyAccount;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;

#[Layout('bunny-sidecar::layouts.app')]
class Show extends Component
{
    use ResolvesCurrentUser;

    public BunnyAccount $account;

    public string $domain;

    public int $zoneId;

    public ?int $pendingDeleteId = null;

    /**
     * Manager user ids per bunny record id, bound to the per-row dropdowns.
     *
     * @var array<int|string, list<int|string>>
     */
    public array $managerIds = [];

    public function mount(BunnyAccount $account, string $domain, ZoneDirectory $directory, ZoneAccess $access): void
    {
        abort_unless($access->canViewZone($this->currentUser(), $account, $domain), 403);

        $zone = $directory->find($account, $domain);
        abort_if($zone === null, 404);

        $this->account = $account;
        $this->domain = $zone->domain;
        $this->zoneId = $zone->id;
    }

    public function confirmDelete(int $recordId, ZoneDirectory $directory, ZoneAccess $access): void
    {
        $record = $this->findRecord($directory, $recordId);
        abort_if($record === null, 404);

        abort_unless($access->canManageRecord($this->currentUser(), $this->account, $this->domain, $record), 403);

        $this->pendingDeleteId = $recordId;
        Flux::modal('delete-record')->show();
    }

    /**
     * Livewire hook for the per-record manager dropdowns (wire:model on managerIds.{recordId}).
     */
    public function updatedManagerIds(mixed $value, string $key, RecordManagers $managers, ZoneDirectory $directory, ZoneAccess $access): void
    {
        abort_unless($access->canAssignRecordManagers($this->currentUser(), $this->account, $this->domain), 403);

        $recordId = (int) explode('.', $key, 2)[0];
        abort_if($this->findRecord($directory, $recordId) === null, 404);

        $eligibleIds = $managers->eligibleMembers($this->account, $this->domain)->pluck('id')->all();
        $userIds = array_values(collect($this->managerIds[$recordId] ?? [])
            ->map(fn (mixed $id): int => (int) $id)
            ->filter(fn (int $id): bool => in_array($id, $eligibleIds, true))
            ->all());

        $managers->sync($this->account, $this->domain, $recordId, $userIds);
    }

    public function deleteRecord(ZoneDirectory $directory, RecordOwnerships $ownerships, RecordManagers $managers, ZoneAccess $access): void
    {
        $record = $this->pendingDeleteId === null ? null : $this->findRecord($directory, $this->pendingDeleteId);
        abort_if($record === null, 404);

        abort_unless($access->canManageRecord($this->currentUser(), $this->account, $this->domain, $record), 403);

        try {
            BunnyClient::for($this->account)->deleteRecord($this->zoneId, $record->id);
        } catch (BunnyApiException $exception) {
            Flux::toast(variant: 'danger', text: $exception->userMessage());

            return;
        }

        $ownerships->release($this->account, $this->domain, $record->id);
        $managers->release($this->account, $this->domain, $record->id);
        $directory->forgetRecords($this->account, $this->zoneId);
        $this->pendingDeleteId = null;

        Flux::modal('delete-record')->close();
        Flux::toast(variant: 'success', text: __('DNS record deleted.'));
    }

    public function refreshRecords(ZoneDirectory $directory): void
    {
        $directory->forgetRecords($this->account, $this->zoneId);
    }

    #[On('record-saved')]
    public function recordSaved(): void
    {
        // Re-render with the freshly busted record cache.
    }

    public function render(ZoneDirectory $directory, RecordManagers $managers, ZoneAccess $access): View
    {
        $user = $this->currentUser();
        $apiError = null;
        $rows = collect();
        $members = $access->canAssignRecordManagers($user, $this->account, $this->domain)
            ? $managers->eligibleMembers($this->account, $this->domain)
            : collect();
        // The managers column only makes sense when there is somebody to assign.
        $canAssignManagers = $members->isNotEmpty();

        try {
            $zone = $directory->zoneWithRecords($this->account, $this->zoneId);

            $rows = collect($zone->records)
                ->sortBy([
                    fn (DnsRecord $a, DnsRecord $b): int => strcasecmp($a->type->label(), $b->type->label()),
                    fn (DnsRecord $a, DnsRecord $b): int => strcasecmp($a->name, $b->name),
                ])
                ->map(fn (DnsRecord $record): array => [
                    'record' => $record,
                    'canManage' => $access->canManageRecord($user, $this->account, $this->domain, $record),
                ])
                ->values();

            if ($canAssignManagers) {
                $assigned = $managers->forZone($this->account, $this->domain);
                $this->managerIds = $rows
                    ->mapWithKeys(fn (array $row): array => [$row['record']->id => $assigned[$row['record']->id] ?? []])
                    ->all();
            }
        } catch (BunnyApiException $exception) {
            $apiError = $exception->userMessage();
        }

        return view('bunny-sidecar::livewire.zones.show', [
            'rows' => $rows,
            'apiError' => $apiError,
            'canAdd' => $access->canAddAnyRecord($user, $this->account, $this->domain),
            'canAssignManagers' => $canAssignManagers,
            'members' => $members,
        ])->title($this->domain);
    }

    protected function findRecord(ZoneDirectory $directory, int $recordId): ?DnsRecord
    {
        try {
            $records = $directory->zoneWithRecords($this->account, $this->zoneId)->records;
        } catch (BunnyApiException) {
            return null;
        }

        return collect($records)->first(fn (DnsRecord $record): bool => $record->id === $recordId);
    }
}
