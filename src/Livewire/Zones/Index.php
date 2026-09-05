<?php

declare(strict_types=1);

namespace BunnySidecar\BunnySidecar\Livewire\Zones;

use BunnySidecar\BunnySidecar\Access\ZoneAccess;
use BunnySidecar\BunnySidecar\Bunny\BunnyApiException;
use BunnySidecar\BunnySidecar\Bunny\Dtos\DnsZone;
use BunnySidecar\BunnySidecar\Bunny\ZoneDirectory;
use BunnySidecar\BunnySidecar\Livewire\Concerns\ResolvesCurrentUser;
use BunnySidecar\BunnySidecar\Models\BunnyAccount;
use BunnySidecar\BunnySidecar\Models\DnsZoneGrant;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('bunny-sidecar::layouts.app')]
#[Title('Zones')]
class Index extends Component
{
    use ResolvesCurrentUser;

    public string $search = '';

    public function refreshZones(ZoneDirectory $directory, ZoneAccess $access): void
    {
        $this->accessibleAccounts($access)->each(
            fn (BunnyAccount $account) => $directory->forgetZones($account),
        );
    }

    public function render(ZoneDirectory $directory, ZoneAccess $access): View
    {
        $apiError = null;
        $rows = collect();

        try {
            $rows = $this->zoneRows($directory, $access);
        } catch (BunnyApiException $exception) {
            $apiError = $exception->userMessage();
        }

        if ($this->search !== '') {
            $rows = $rows->filter(fn (array $row): bool => Str::contains(
                mb_strtolower($row['zone']->domain),
                mb_strtolower($this->search),
            ))->values();
        }

        return view('bunny-sidecar::livewire.zones.index', [
            'rows' => $rows,
            'apiError' => $apiError,
            'isAdmin' => $this->currentUser()->isAdmin(),
        ]);
    }

    /**
     * @return Collection<int, array{account: BunnyAccount, zone: DnsZone}>
     */
    protected function zoneRows(ZoneDirectory $directory, ZoneAccess $access): Collection
    {
        $user = $this->currentUser();

        if ($user->isAdmin()) {
            return $directory->all();
        }

        return $access->grantsFor($user)
            ->map(function (DnsZoneGrant $grant) use ($directory): ?array {
                $zone = $directory->find($grant->bunnyAccount, $grant->zone_domain);

                return $zone === null ? null : ['account' => $grant->bunnyAccount, 'zone' => $zone];
            })
            ->filter()
            ->values();
    }

    /**
     * @return Collection<int, BunnyAccount>
     */
    protected function accessibleAccounts(ZoneAccess $access): Collection
    {
        $user = $this->currentUser();

        if ($user->isAdmin()) {
            return BunnyAccount::query()->get();
        }

        return $access->grantsFor($user)
            ->map(fn (DnsZoneGrant $grant): BunnyAccount => $grant->bunnyAccount)
            ->unique('id')
            ->values();
    }
}
