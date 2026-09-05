<?php

declare(strict_types=1);

use BunnySidecar\BunnySidecar\Enums\DnsEntryType;
use BunnySidecar\BunnySidecar\Tests\TestCase;

uses(TestCase::class)->in(__DIR__);

/*
|--------------------------------------------------------------------------
| bunny.net API fixtures
|--------------------------------------------------------------------------
*/

/**
 * A DNS zone payload as returned by the bunny.net API.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function bunnyZoneFixture(array $overrides = []): array
{
    return array_merge([
        'Id' => 1001,
        'Domain' => 'example.com',
        'Nameserver1' => 'kiki.bunny.net',
        'Nameserver2' => 'coco.bunny.net',
        'Records' => [],
    ], $overrides);
}

/**
 * A DNS record payload as returned by the bunny.net API.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function bunnyRecordFixture(array $overrides = []): array
{
    return array_merge([
        'Id' => 5001,
        'Type' => DnsEntryType::A->value,
        'Name' => 'www',
        'Value' => '192.0.2.10',
        'Ttl' => 300,
        'Priority' => 0,
        'Weight' => 0,
        'Port' => 0,
        'Flags' => 0,
        'Tag' => '',
        'PullZoneId' => 0,
        'ScriptId' => 0,
        'Accelerated' => false,
        'MonitorType' => 0,
        'SmartRoutingType' => 0,
        'Disabled' => false,
        'Comment' => null,
        'AutoSslIssuance' => false,
    ], $overrides);
}

/**
 * A paginated bunny.net list response.
 *
 * @param  list<array<string, mixed>>  $items
 * @return array<string, mixed>
 */
function bunnyPageFixture(array $items, bool $hasMore = false): array
{
    return [
        'Items' => $items,
        'CurrentPage' => 1,
        'TotalItems' => count($items),
        'HasMoreItems' => $hasMore,
    ];
}

/**
 * A pull zone payload as returned by the bunny.net API.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function bunnyPullZoneFixture(array $overrides = []): array
{
    return array_merge([
        'Id' => 7001,
        'Name' => 'example-pullzone',
        'Hostnames' => [
            ['Id' => 1, 'Value' => 'example-pullzone.b-cdn.net', 'IsSystemHostname' => true],
            ['Id' => 2, 'Value' => 'app.example.com', 'IsSystemHostname' => false],
        ],
    ], $overrides);
}

/**
 * A shield zone payload as returned by the bunny.net Shield API.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function bunnyShieldZoneFixture(array $overrides = []): array
{
    return array_merge([
        'shieldZoneId' => 3001,
        'pullZoneId' => 7001,
        'wafEnabled' => true,
        'planType' => 1,
    ], $overrides);
}

/**
 * A paginated bunny.net Shield list response ("data" + "page").
 *
 * @param  list<array<string, mixed>>  $items
 * @return array<string, mixed>
 */
function bunnyShieldPageFixture(array $items, int $currentPage = 1, ?int $nextPage = null): array
{
    return [
        'data' => $items,
        'page' => [
            'totalCount' => count($items),
            'totalPages' => $nextPage ?? $currentPage,
            'currentPage' => $currentPage,
            'nextPage' => $nextPage,
            'pageSize' => 100,
        ],
    ];
}

/**
 * One row of a bunny.net Shield event log search.
 *
 * @param  array<string, mixed>  $fields  overrides for the "fields" object
 * @param  array<string, mixed>  $log  overrides for the "log" object
 * @return array<string, mixed>
 */
function bunnyShieldEventFixture(array $fields = [], array $log = [], ?string $logId = null, ?int $timestamp = null): array
{
    return [
        'logId' => $logId ?? fake()->uuid(),
        'timestamp' => $timestamp ?? 1788350685304,
        'fields' => array_merge([
            'feature' => 'WAF',
            'ruleId' => '942550',
            'action' => 'Logged',
            'method' => 'POST',
            'country' => 'DE',
            'asn' => '3209',
            'ip' => '198.51.100.*',
            'url' => 'https://app.example.com/livewire/update',
            'ua' => 'Mozilla/5.0',
        ], $fields),
        'log' => array_merge([
            'ServerZoneId' => 6,
            'Message' => 'JSON-based SQL Injection',
            'Url' => 'https://app.example.com/livewire/update',
            'RemoteIp' => '198.51.100.*',
            'UrlEncodedBody' => null,
            'Headers' => [
                ['Name' => 'Host', 'Value' => 'app.example.com'],
                ['Name' => 'Content-Type', 'Value' => 'application/json'],
            ],
            'TriggerMatch' => ['Key' => 'RX', 'Value' => '{_token:abc components:[{snapshot:{data:{message:hello world}}}]}'],
        ], $log),
    ];
}

/**
 * A bunny.net Shield event log search response.
 *
 * @param  list<array<string, mixed>>  $rows
 * @return array<string, mixed>
 */
function bunnyShieldSearchFixture(array $rows, int $page = 0, int $totalPages = 1, ?int $total = null): array
{
    return [
        'rows' => $rows,
        'groups' => null,
        'total' => $total ?? count($rows),
        'totalPages' => $totalPages,
        'page' => $page,
        'errorResponse' => null,
    ];
}

/**
 * One aggregated rule group of a Shield event log search with groupBy ruleId.
 *
 * @return array<string, mixed>
 */
function bunnyShieldRuleGroupFixture(string $ruleId = '942550', int $count = 77, int $blocked = 0, int $challenged = 0): array
{
    return [
        'key' => ['ruleId' => $ruleId],
        'count' => $count,
        'context' => [
            'blocked' => $blocked,
            'challenged' => $challenged,
            'logged' => $count - $blocked - $challenged,
        ],
        'firstSeen' => 1788300000000,
        'lastSeen' => 1788350685304,
    ];
}

/**
 * A bunny.net Shield event log search response grouped by rule id.
 *
 * @param  list<array<string, mixed>>  $groups
 * @return array<string, mixed>
 */
function bunnyShieldGroupsFixture(array $groups, int $page = 0, int $totalPages = 1): array
{
    return [
        'rows' => null,
        'groups' => $groups,
        'total' => count($groups),
        'totalPages' => $totalPages,
        'page' => $page,
        'errorResponse' => null,
    ];
}
