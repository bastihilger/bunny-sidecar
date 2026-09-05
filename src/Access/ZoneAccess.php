<?php

declare(strict_types=1);

namespace BunnySidecar\BunnySidecar\Access;

use BunnySidecar\BunnySidecar\Bunny\Dtos\DnsRecord;
use BunnySidecar\BunnySidecar\Enums\DnsEntryType;
use BunnySidecar\BunnySidecar\Enums\DnsRecordScope;
use BunnySidecar\BunnySidecar\Models\BunnyAccount;
use BunnySidecar\BunnySidecar\Models\DnsZoneGrant;
use BunnySidecar\BunnySidecar\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

/**
 * Access checks for DNS zones and records. Admins may do everything; the
 * member rules are encoded here. Zones are not local models, they are
 * identified by (bunny account, domain string).
 */
class ZoneAccess
{
    /**
     * Grants memoized per user for the current request, since the checks
     * run once per rendered record row and action.
     *
     * @var array<int, EloquentCollection<string, DnsZoneGrant>>
     */
    private array $grantCache = [];

    public function __construct(
        private RecordOwnerships $ownerships,
        private RecordManagers $managers,
    ) {}

    public function canViewZone(User $user, BunnyAccount $account, string $domain): bool
    {
        return $user->isAdmin() || $this->grantFor($user, $account, $domain) !== null;
    }

    public function canAddRecord(User $user, BunnyAccount $account, string $domain, DnsEntryType $type, DnsRecordScope $scope): bool
    {
        return $user->isAdmin()
            || ($this->grantFor($user, $account, $domain)?->permissions->allows($scope, $type) ?? false);
    }

    /**
     * Whether the user may add at least one record type in at least one scope.
     */
    public function canAddAnyRecord(User $user, BunnyAccount $account, string $domain): bool
    {
        return $user->isAdmin()
            || ($this->grantFor($user, $account, $domain)?->permissions->allowsAnywhere() ?? false);
    }

    /**
     * Editing and deleting an existing record is allowed for members who are
     * explicitly assigned as its managers, or who created it themselves and
     * may still add records of its type in its scope. Both require a grant on
     * the zone, since without one the member cannot see the zone at all.
     */
    public function canManageRecord(User $user, BunnyAccount $account, string $domain, DnsRecord $record): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        $permissions = $this->grantFor($user, $account, $domain)?->permissions;

        if ($permissions === null) {
            return false;
        }

        if ($this->managers->isManager($user, $account, $domain, $record->id)) {
            return true;
        }

        return $permissions->allows($record->scope(), $record->type)
            && $this->ownerships->owns($user, $account, $domain, $record->id);
    }

    /**
     * Only admins assign per-record managers.
     */
    public function canAssignRecordManagers(User $user, BunnyAccount $account, string $domain): bool
    {
        return $user->isAdmin();
    }

    public function grantFor(User $user, BunnyAccount $account, string $domain): ?DnsZoneGrant
    {
        return $this->grantsFor($user)->get($this->key($account->id, $domain));
    }

    /**
     * All grants of the user, keyed by "{bunny_account_id}:{zone_domain}".
     *
     * @return EloquentCollection<string, DnsZoneGrant>
     */
    public function grantsFor(User $user): EloquentCollection
    {
        return $this->grantCache[$user->id] ??= $user->zoneGrants()
            ->with('bunnyAccount')
            ->get()
            ->keyBy(fn (DnsZoneGrant $grant): string => $this->key($grant->bunny_account_id, $grant->zone_domain));
    }

    public function forgetGrants(User $user): void
    {
        unset($this->grantCache[$user->id]);
    }

    public function key(int $bunnyAccountId, string $domain): string
    {
        return $bunnyAccountId.':'.mb_strtolower($domain);
    }
}
