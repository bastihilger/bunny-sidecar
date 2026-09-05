<?php

declare(strict_types=1);

namespace BunnySidecar\BunnySidecar\Access;

use BunnySidecar\BunnySidecar\Models\BunnyAccount;
use BunnySidecar\BunnySidecar\Models\DnsRecordManager;
use BunnySidecar\BunnySidecar\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Explicit per-record manager assignments. They take precedence over zone
 * grants: an assigned member may edit and delete that record no matter who
 * created it. Assignments are memoized per zone for the current request
 * because the check runs once per rendered record row.
 */
class RecordManagers
{
    /**
     * @var array<string, array<int, list<int>>>
     */
    private array $zoneCache = [];

    public function isManager(User $user, BunnyAccount $account, string $domain, int $recordId): bool
    {
        return in_array($user->id, $this->userIdsFor($account, $domain, $recordId), true);
    }

    /**
     * The ids of the members explicitly assigned to one record.
     *
     * @return list<int>
     */
    public function userIdsFor(BunnyAccount $account, string $domain, int $recordId): array
    {
        return $this->forZone($account, $domain)[$recordId] ?? [];
    }

    /**
     * Manager user ids of every record in the zone, keyed by bunny record id.
     *
     * @return array<int, list<int>>
     */
    public function forZone(BunnyAccount $account, string $domain): array
    {
        $key = $this->key($account, $domain);

        if (! isset($this->zoneCache[$key])) {
            $managers = [];

            foreach (DnsRecordManager::query()
                ->where('bunny_account_id', $account->id)
                ->where('zone_domain', mb_strtolower($domain))
                ->get(['record_id', 'user_id']) as $manager) {
                $managers[$manager->record_id][] = $manager->user_id;
            }

            $this->zoneCache[$key] = $managers;
        }

        return $this->zoneCache[$key];
    }

    /**
     * Replace the managers of one record with the given user ids.
     *
     * @param  list<int>  $userIds
     */
    public function sync(BunnyAccount $account, string $domain, int $recordId, array $userIds): void
    {
        $domain = mb_strtolower($domain);
        $userIds = array_values(array_unique(array_map(intval(...), $userIds)));

        DB::transaction(function () use ($account, $domain, $recordId, $userIds): void {
            DnsRecordManager::query()
                ->where('bunny_account_id', $account->id)
                ->where('zone_domain', $domain)
                ->where('record_id', $recordId)
                ->whereNotIn('user_id', $userIds)
                ->delete();

            foreach ($userIds as $userId) {
                DnsRecordManager::query()->firstOrCreate([
                    'bunny_account_id' => $account->id,
                    'zone_domain' => $domain,
                    'record_id' => $recordId,
                    'user_id' => $userId,
                ]);
            }
        });

        unset($this->zoneCache[$this->key($account, $domain)]);
    }

    /**
     * Drop every assignment of a record, e.g. after it was deleted at bunny.net.
     */
    public function release(BunnyAccount $account, string $domain, int $recordId): void
    {
        DnsRecordManager::query()
            ->where('bunny_account_id', $account->id)
            ->where('zone_domain', mb_strtolower($domain))
            ->where('record_id', $recordId)
            ->delete();

        unset($this->zoneCache[$this->key($account, $domain)]);
    }

    /**
     * Members who can be assigned as managers in a zone: everyone holding a
     * grant on it. Admins manage everything anyway and are never listed.
     *
     * @return Collection<int, User>
     */
    public function eligibleMembers(BunnyAccount $account, string $domain): Collection
    {
        return User::query()
            ->whereHas('zoneGrants', fn (Builder $query): Builder => $query
                ->where('bunny_account_id', $account->id)
                ->where('zone_domain', mb_strtolower($domain)))
            ->orderBy('name')
            ->get()
            ->reject(fn (User $user): bool => $user->isAdmin())
            ->values();
    }

    private function key(BunnyAccount $account, string $domain): string
    {
        return $account->id.':'.mb_strtolower($domain);
    }
}
