<?php

declare(strict_types=1);

use BunnySidecar\BunnySidecar\Dtos\ZoneGrantPermissions;
use BunnySidecar\BunnySidecar\Enums\DnsEntryType;
use BunnySidecar\BunnySidecar\Enums\DnsRecordScope;
use BunnySidecar\BunnySidecar\Models\DnsZoneGrant;

test('allows only the configured types per scope', function (): void {
    $permissions = ZoneGrantPermissions::fromArray([
        'root' => ZoneGrantPermissions::ALL_TYPES,
        'subdomain' => [DnsEntryType::A->value, DnsEntryType::TXT->value],
    ]);

    expect($permissions->allows(DnsRecordScope::Root, DnsEntryType::CAA))->toBeTrue()
        ->and($permissions->allowsAll(DnsRecordScope::Root))->toBeTrue()
        ->and($permissions->allows(DnsRecordScope::Subdomain, DnsEntryType::A))->toBeTrue()
        ->and($permissions->allows(DnsRecordScope::Subdomain, DnsEntryType::CNAME))->toBeFalse()
        ->and($permissions->allowsAny(DnsRecordScope::Subdomain))->toBeTrue()
        ->and($permissions->allowsAll(DnsRecordScope::Subdomain))->toBeFalse()
        ->and($permissions->allowsAnywhere())->toBeTrue();
});

test('missing scopes allow nothing', function (): void {
    $permissions = ZoneGrantPermissions::fromArray(['root' => [DnsEntryType::MX->value]]);

    expect($permissions->allows(DnsRecordScope::Subdomain, DnsEntryType::MX))->toBeFalse()
        ->and($permissions->allowsAny(DnsRecordScope::Subdomain))->toBeFalse()
        ->and(ZoneGrantPermissions::none()->allowsAnywhere())->toBeFalse();
});

test('unknown keys are ignored', function (): void {
    $permissions = ZoneGrantPermissions::fromArray(['add' => ['root' => ZoneGrantPermissions::ALL_TYPES], 'manage' => []]);

    expect($permissions->allowsAnywhere())->toBeFalse();
});

test('typesFor expands the wildcard to all cases', function (): void {
    $permissions = ZoneGrantPermissions::fromArray(['root' => ZoneGrantPermissions::ALL_TYPES]);

    expect($permissions->typesFor(DnsRecordScope::Root))->toBe(DnsEntryType::cases())
        ->and($permissions->typesFor(DnsRecordScope::Subdomain))->toBe([]);
});

test('rejects unknown record type values', function (): void {
    ZoneGrantPermissions::fromArray(['root' => [999]]);
})->throws(ValueError::class);

test('rejects non-list scope values', function (): void {
    ZoneGrantPermissions::fromArray(['root' => 'all']);
})->throws(InvalidArgumentException::class);

test('survives a database round-trip through the model cast', function (): void {
    $grant = DnsZoneGrant::factory()
        ->allowIn(DnsRecordScope::Subdomain, DnsEntryType::A, DnsEntryType::TXT)
        ->allowIn(DnsRecordScope::Root)
        ->create();

    $fresh = $grant->fresh();

    expect($fresh?->permissions->allows(DnsRecordScope::Subdomain, DnsEntryType::A))->toBeTrue()
        ->and($fresh?->permissions->allows(DnsRecordScope::Subdomain, DnsEntryType::CNAME))->toBeFalse()
        ->and($fresh?->permissions->allowsAll(DnsRecordScope::Root))->toBeTrue();
});
