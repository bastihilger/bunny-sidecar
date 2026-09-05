<?php

declare(strict_types=1);

namespace BunnySidecar\BunnySidecar\Access;

use BunnySidecar\BunnySidecar\Models\BunnyAccount;
use BunnySidecar\BunnySidecar\Models\DnsRecordOwnership;
use BunnySidecar\BunnySidecar\Models\User;

/**
 * Tracks which member created which bunny.net DNS record through the panel,
 * so members with an "add" grant can manage their own records. Owned record
 * ids are memoized per user and zone for the current request because the
 * check runs once per rendered record row.
 */
class RecordOwnerships
{
    /**
     * @var array<string, list<int>>
     */
    private array $ownedCache = [];

    public function claim(User $user, BunnyAccount $account, string $domain, int $recordId): void
    {
        DnsRecordOwnership::query()->updateOrCreate(
            ['bunny_account_id' => $account->id, 'zone_domain' => mb_strtolower($domain), 'record_id' => $recordId],
            ['created_by' => $user->id],
        );

        unset($this->ownedCache[$this->key($user, $account, $domain)]);
    }

    public function release(BunnyAccount $account, string $domain, int $recordId): void
    {
        DnsRecordOwnership::query()
            ->where('bunny_account_id', $account->id)
            ->where('zone_domain', mb_strtolower($domain))
            ->where('record_id', $recordId)
            ->delete();

        $this->ownedCache = [];
    }

    public function owns(User $user, BunnyAccount $account, string $domain, int $recordId): bool
    {
        return in_array($recordId, $this->ownedRecordIds($user, $account, $domain), true);
    }

    /**
     * @return list<int>
     */
    public function ownedRecordIds(User $user, BunnyAccount $account, string $domain): array
    {
        return $this->ownedCache[$this->key($user, $account, $domain)] ??= array_values(DnsRecordOwnership::query()
            ->where('bunny_account_id', $account->id)
            ->where('zone_domain', mb_strtolower($domain))
            ->where('created_by', $user->id)
            ->pluck('record_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all());
    }

    private function key(User $user, BunnyAccount $account, string $domain): string
    {
        return $user->id.':'.$account->id.':'.mb_strtolower($domain);
    }
}
