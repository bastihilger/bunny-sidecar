<?php

declare(strict_types=1);

use BunnySidecar\BunnySidecar\Access\ZoneAccess;
use BunnySidecar\BunnySidecar\Bunny\Dtos\DnsRecord;
use BunnySidecar\BunnySidecar\Enums\DnsEntryType;
use BunnySidecar\BunnySidecar\Enums\DnsRecordScope;
use BunnySidecar\BunnySidecar\Models\BunnyAccount;
use BunnySidecar\BunnySidecar\Models\DnsRecordManager;
use BunnySidecar\BunnySidecar\Models\DnsRecordOwnership;
use BunnySidecar\BunnySidecar\Models\DnsZoneGrant;
use BunnySidecar\BunnySidecar\Models\User;

function dnsRecord(int $id, DnsEntryType $type = DnsEntryType::A, string $name = 'www'): DnsRecord
{
    return DnsRecord::fromApi(bunnyRecordFixture(['Id' => $id, 'Type' => $type->value, 'Name' => $name]));
}

beforeEach(function (): void {
    $this->access = app(ZoneAccess::class);
});

test('admins pass every zone and record check', function (): void {
    $admin = User::factory()->admin()->create();
    $account = BunnyAccount::factory()->create();

    expect($this->access->canViewZone($admin, $account, 'example.com'))->toBeTrue()
        ->and($this->access->canAddRecord($admin, $account, 'example.com', DnsEntryType::CAA, DnsRecordScope::Root))->toBeTrue()
        ->and($this->access->canAddAnyRecord($admin, $account, 'example.com'))->toBeTrue()
        ->and($this->access->canManageRecord($admin, $account, 'example.com', dnsRecord(1, DnsEntryType::CAA)))->toBeTrue()
        ->and($this->access->canAssignRecordManagers($admin, $account, 'example.com'))->toBeTrue();
});

test('members can only view zones they are granted', function (): void {
    $member = User::factory()->member()->create();
    $grant = DnsZoneGrant::factory()->for($member)->create(['zone_domain' => 'granted.com']);
    $otherAccount = BunnyAccount::factory()->create();

    expect($this->access->canViewZone($member, $grant->bunnyAccount, 'granted.com'))->toBeTrue()
        ->and($this->access->canViewZone($member, $grant->bunnyAccount, 'other.com'))->toBeFalse()
        ->and($this->access->canViewZone($member, $otherAccount, 'granted.com'))->toBeFalse()
        ->and($this->access->canAssignRecordManagers($member, $grant->bunnyAccount, 'granted.com'))->toBeFalse();
});

test('zone domains are matched case-insensitively', function (): void {
    $member = User::factory()->member()->create();
    $grant = DnsZoneGrant::factory()->for($member)->create(['zone_domain' => 'Granted.COM']);

    expect($this->access->canViewZone($member, $grant->bunnyAccount, 'granted.com'))->toBeTrue();
});

test('adding respects the per-scope type grants', function (): void {
    $member = User::factory()->member()->create();
    $grant = DnsZoneGrant::factory()
        ->for($member)
        ->allowIn(DnsRecordScope::Subdomain, DnsEntryType::A, DnsEntryType::CNAME)
        ->allowIn(DnsRecordScope::Root, DnsEntryType::TXT)
        ->create(['zone_domain' => 'granted.com']);

    $account = $grant->bunnyAccount;

    expect($this->access->canAddRecord($member, $account, 'granted.com', DnsEntryType::A, DnsRecordScope::Subdomain))->toBeTrue()
        ->and($this->access->canAddRecord($member, $account, 'granted.com', DnsEntryType::A, DnsRecordScope::Root))->toBeFalse()
        ->and($this->access->canAddRecord($member, $account, 'granted.com', DnsEntryType::TXT, DnsRecordScope::Root))->toBeTrue()
        ->and($this->access->canAddRecord($member, $account, 'granted.com', DnsEntryType::TXT, DnsRecordScope::Subdomain))->toBeFalse()
        ->and($this->access->canAddAnyRecord($member, $account, 'granted.com'))->toBeTrue();
});

test('a member without any add grant may not add anywhere and cannot manage foreign records', function (): void {
    $member = User::factory()->member()->create();
    $grant = DnsZoneGrant::factory()->for($member)->create(['zone_domain' => 'granted.com']);

    expect($this->access->canAddAnyRecord($member, $grant->bunnyAccount, 'granted.com'))->toBeFalse()
        ->and($this->access->canManageRecord($member, $grant->bunnyAccount, 'granted.com', dnsRecord(1)))->toBeFalse();
});

test('an add grant lets members manage the records they created themselves in that scope', function (): void {
    $member = User::factory()->member()->create();
    $grant = DnsZoneGrant::factory()
        ->for($member)
        ->allowIn(DnsRecordScope::Subdomain, DnsEntryType::A)
        ->create(['zone_domain' => 'granted.com']);
    $account = $grant->bunnyAccount;

    foreach ([10, 11, 12, 13] as $recordId) {
        DnsRecordOwnership::factory()->for($account)->for($member, 'creator')->create(['zone_domain' => 'granted.com', 'record_id' => $recordId]);
    }
    DnsRecordOwnership::factory()->for($account)->create(['zone_domain' => 'granted.com', 'record_id' => 14]);
    DnsRecordOwnership::factory()->for($account)->for($member, 'creator')->create(['zone_domain' => 'other.com', 'record_id' => 15]);

    expect($this->access->canManageRecord($member, $account, 'Granted.COM', dnsRecord(10, DnsEntryType::A, 'www')))->toBeTrue()
        ->and($this->access->canManageRecord($member, $account, 'granted.com', dnsRecord(11, DnsEntryType::TXT, 'www')))->toBeFalse()
        ->and($this->access->canManageRecord($member, $account, 'granted.com', dnsRecord(12, DnsEntryType::A, '')))->toBeFalse()
        ->and($this->access->canManageRecord($member, $account, 'granted.com', dnsRecord(13, DnsEntryType::A, '@')))->toBeFalse()
        ->and($this->access->canManageRecord($member, $account, 'granted.com', dnsRecord(14, DnsEntryType::A, 'www')))->toBeFalse()
        ->and($this->access->canManageRecord($member, $account, 'granted.com', dnsRecord(15, DnsEntryType::A, 'www')))->toBeFalse()
        ->and($this->access->canManageRecord($member, $account, 'granted.com', dnsRecord(16, DnsEntryType::A, 'www')))->toBeFalse();
});

test('an explicit manager assignment takes precedence over the grant', function (): void {
    $member = User::factory()->member()->create();
    $grant = DnsZoneGrant::factory()->for($member)->create(['zone_domain' => 'granted.com']);
    $account = $grant->bunnyAccount;

    DnsRecordManager::factory()->for($account)->for($member)->create(['zone_domain' => 'granted.com', 'record_id' => 20]);
    DnsRecordManager::factory()->for($account)->create(['zone_domain' => 'granted.com', 'record_id' => 21]);

    expect($this->access->canManageRecord($member, $account, 'granted.com', dnsRecord(20, DnsEntryType::CAA, '')))->toBeTrue()
        ->and($this->access->canManageRecord($member, $account, 'granted.com', dnsRecord(21, DnsEntryType::A)))->toBeFalse();
});

test('a manager assignment without a zone grant gives no access', function (): void {
    $member = User::factory()->member()->create();
    $account = BunnyAccount::factory()->create();

    DnsRecordManager::factory()->for($account)->for($member)->create(['zone_domain' => 'granted.com', 'record_id' => 20]);

    expect($this->access->canManageRecord($member, $account, 'granted.com', dnsRecord(20)))->toBeFalse();
});

test('a grant on one zone gives no access to other zones of the account', function (): void {
    $member = User::factory()->member()->create();
    $grant = DnsZoneGrant::factory()->for($member)->allowAll()->create(['zone_domain' => 'granted.com']);

    expect($this->access->canAddRecord($member, $grant->bunnyAccount, 'other.com', DnsEntryType::A, DnsRecordScope::Root))->toBeFalse()
        ->and($this->access->canManageRecord($member, $grant->bunnyAccount, 'other.com', dnsRecord(1)))->toBeFalse();
});
