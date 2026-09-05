<?php

declare(strict_types=1);

namespace BunnySidecar\BunnySidecar\Enums;

/**
 * DNS record types as defined by the bunny.net API (integer backed,
 * values must match the API's Type field exactly).
 */
enum DnsEntryType: int
{
    case A = 0;
    case AAAA = 1;
    case CNAME = 2;
    case TXT = 3;
    case MX = 4;
    case Redirect = 5;
    case Flatten = 6;
    case PullZone = 7;
    case SRV = 8;
    case CAA = 9;
    case PTR = 10;
    case Script = 11;
    case NS = 12;
    case SVCB = 13;
    case HTTPS = 14;
    case TLSA = 15;

    private const string HOSTNAME_REGEX = '/^(?=.{1,253}\.?$)([a-zA-Z0-9_]([a-zA-Z0-9\-_]{0,61}[a-zA-Z0-9_])?\.)*[a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?\.?$/';

    public function label(): string
    {
        return match ($this) {
            self::Redirect => __('Redirect'),
            self::Flatten => __('Flatten'),
            self::PullZone => __('Pull Zone'),
            self::Script => __('Script'),
            default => $this->name,
        };
    }

    /**
     * The form fields available for this record type. Fields shared by every
     * type (name, TTL, disabled, comment) come first, type specifics after.
     *
     * @return list<DnsRecordField>
     */
    public function fields(): array
    {
        $base = [DnsRecordField::Name, DnsRecordField::Ttl, DnsRecordField::Disabled, DnsRecordField::Comment];

        $value = match ($this) {
            self::PullZone => [DnsRecordField::PullZoneId],
            self::Script => [DnsRecordField::ScriptId],
            default => [DnsRecordField::Value],
        };

        $advanced = match ($this) {
            self::A, self::AAAA, self::CNAME => [
                DnsRecordField::Accelerated,
                DnsRecordField::MonitorType,
                DnsRecordField::SmartRoutingType,
                DnsRecordField::GeolocationLatitude,
                DnsRecordField::GeolocationLongitude,
                DnsRecordField::LatencyZone,
            ],
            self::MX => [DnsRecordField::Priority],
            self::SRV => [DnsRecordField::Priority, DnsRecordField::Weight, DnsRecordField::Port],
            self::CAA => [DnsRecordField::Flags, DnsRecordField::Tag],
            default => [],
        };

        return [...$base, ...$value, ...$advanced];
    }

    public function hasField(DnsRecordField $field): bool
    {
        return in_array($field, $this->fields(), true);
    }

    /**
     * Type-specific validation rules, keyed by DnsRecordForm property name.
     * Base rules (name, ttl, disabled, comment) live in the form itself.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return match ($this) {
            self::A => ['value' => ['required', 'ipv4']],
            self::AAAA => ['value' => ['required', 'ipv6']],
            self::CNAME, self::NS, self::PTR, self::Flatten => [
                'value' => ['required', 'string', 'max:253', 'regex:'.self::HOSTNAME_REGEX],
            ],
            self::TXT => ['value' => ['required', 'string', 'max:4096']],
            self::MX => [
                'value' => ['required', 'string', 'max:253', 'regex:'.self::HOSTNAME_REGEX],
                'priority' => ['required', 'integer', 'between:0,65535'],
            ],
            self::Redirect => ['value' => ['required', 'url', 'max:2048']],
            self::PullZone => ['pullZoneId' => ['required', 'integer', 'min:1']],
            self::Script => ['scriptId' => ['required', 'integer', 'min:1']],
            self::SRV => [
                'value' => ['required', 'string', 'max:253'],
                'priority' => ['required', 'integer', 'between:0,65535'],
                'weight' => ['required', 'integer', 'between:0,65535'],
                'port' => ['required', 'integer', 'between:1,65535'],
            ],
            self::CAA => [
                'value' => ['required', 'string', 'max:512'],
                'flags' => ['required', 'integer', 'in:0,128'],
                'tag' => ['required', 'string', 'in:issue,issuewild,iodef'],
            ],
            self::SVCB, self::HTTPS => [
                'value' => ['required', 'string', 'max:4096'],
                'priority' => ['nullable', 'integer', 'between:0,65535'],
            ],
            self::TLSA => ['value' => ['required', 'string', 'max:4096']],
        };
    }

    /**
     * Options for select fields.
     *
     * @return array<int, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $type): array => [$type->value => $type->label()])
            ->all();
    }
}
