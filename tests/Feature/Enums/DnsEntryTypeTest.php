<?php

declare(strict_types=1);

use BunnySidecar\BunnySidecar\Enums\DnsEntryType;
use BunnySidecar\BunnySidecar\Enums\DnsRecordField;

test('backing values match the bunny.net api type mapping', function (): void {
    expect(DnsEntryType::A->value)->toBe(0)
        ->and(DnsEntryType::AAAA->value)->toBe(1)
        ->and(DnsEntryType::CNAME->value)->toBe(2)
        ->and(DnsEntryType::TXT->value)->toBe(3)
        ->and(DnsEntryType::MX->value)->toBe(4)
        ->and(DnsEntryType::Redirect->value)->toBe(5)
        ->and(DnsEntryType::Flatten->value)->toBe(6)
        ->and(DnsEntryType::PullZone->value)->toBe(7)
        ->and(DnsEntryType::SRV->value)->toBe(8)
        ->and(DnsEntryType::CAA->value)->toBe(9)
        ->and(DnsEntryType::PTR->value)->toBe(10)
        ->and(DnsEntryType::Script->value)->toBe(11)
        ->and(DnsEntryType::NS->value)->toBe(12)
        ->and(DnsEntryType::SVCB->value)->toBe(13)
        ->and(DnsEntryType::HTTPS->value)->toBe(14)
        ->and(DnsEntryType::TLSA->value)->toBe(15)
        ->and(DnsEntryType::cases())->toHaveCount(16);
});

test('every type exposes the base fields', function (DnsEntryType $type): void {
    foreach ([DnsRecordField::Name, DnsRecordField::Ttl, DnsRecordField::Disabled, DnsRecordField::Comment] as $field) {
        expect($type->hasField($field))->toBeTrue();
    }
})->with(DnsEntryType::cases());

test('type specific fields follow the bunny.net record model', function (): void {
    expect(DnsEntryType::SRV->hasField(DnsRecordField::Priority))->toBeTrue()
        ->and(DnsEntryType::SRV->hasField(DnsRecordField::Weight))->toBeTrue()
        ->and(DnsEntryType::SRV->hasField(DnsRecordField::Port))->toBeTrue()
        ->and(DnsEntryType::MX->hasField(DnsRecordField::Priority))->toBeTrue()
        ->and(DnsEntryType::MX->hasField(DnsRecordField::Weight))->toBeFalse()
        ->and(DnsEntryType::CAA->hasField(DnsRecordField::Flags))->toBeTrue()
        ->and(DnsEntryType::CAA->hasField(DnsRecordField::Tag))->toBeTrue()
        ->and(DnsEntryType::A->hasField(DnsRecordField::Accelerated))->toBeTrue()
        ->and(DnsEntryType::TXT->hasField(DnsRecordField::Accelerated))->toBeFalse()
        ->and(DnsEntryType::PullZone->hasField(DnsRecordField::PullZoneId))->toBeTrue()
        ->and(DnsEntryType::PullZone->hasField(DnsRecordField::Value))->toBeFalse()
        ->and(DnsEntryType::Script->hasField(DnsRecordField::ScriptId))->toBeTrue()
        ->and(DnsEntryType::Script->hasField(DnsRecordField::Value))->toBeFalse();
});

test('validation rules cover type specific constraints', function (DnsEntryType $type, array $valid, array $invalid): void {
    $rules = $type->rules();

    expect(validator($valid, $rules)->passes())->toBeTrue()
        ->and(validator($invalid, $rules)->fails())->toBeTrue();
})->with([
    'A accepts ipv4 only' => [DnsEntryType::A, ['value' => '192.0.2.10'], ['value' => 'not-an-ip']],
    'AAAA accepts ipv6 only' => [DnsEntryType::AAAA, ['value' => '2001:db8::1'], ['value' => '192.0.2.10']],
    'CNAME accepts hostnames' => [DnsEntryType::CNAME, ['value' => 'www.example.com'], ['value' => 'not valid host!']],
    'MX requires priority' => [DnsEntryType::MX, ['value' => 'mail.example.com', 'priority' => 10], ['value' => 'mail.example.com']],
    'SRV requires port in range' => [
        DnsEntryType::SRV,
        ['value' => 'sip.example.com', 'priority' => 0, 'weight' => 5, 'port' => 5060],
        ['value' => 'sip.example.com', 'priority' => 0, 'weight' => 5, 'port' => 70000],
    ],
    'CAA restricts tag' => [
        DnsEntryType::CAA,
        ['value' => 'letsencrypt.org', 'flags' => 0, 'tag' => 'issue'],
        ['value' => 'letsencrypt.org', 'flags' => 0, 'tag' => 'bogus'],
    ],
    'Redirect requires a url' => [DnsEntryType::Redirect, ['value' => 'https://example.com/target'], ['value' => 'nope']],
    'PullZone requires a numeric id' => [DnsEntryType::PullZone, ['pullZoneId' => 42], ['pullZoneId' => 'abc']],
]);

test('options lists every case keyed by backing value', function (): void {
    $options = DnsEntryType::options();

    expect($options)->toHaveCount(16)
        ->and($options[0])->toBe('A')
        ->and($options[7])->toBe('Pull Zone');
});
