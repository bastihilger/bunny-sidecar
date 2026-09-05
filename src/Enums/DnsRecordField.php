<?php

declare(strict_types=1);

namespace BunnySidecar\BunnySidecar\Enums;

/**
 * Form fields a DNS record can expose. Which fields are shown for a given
 * record type is defined by DnsEntryType::fields().
 */
enum DnsRecordField: string
{
    case Name = 'name';
    case Value = 'value';
    case Ttl = 'ttl';
    case Priority = 'priority';
    case Weight = 'weight';
    case Port = 'port';
    case Flags = 'flags';
    case Tag = 'tag';
    case PullZoneId = 'pullZoneId';
    case ScriptId = 'scriptId';
    case Accelerated = 'accelerated';
    case Disabled = 'disabled';
    case Comment = 'comment';
    case MonitorType = 'monitorType';
    case SmartRoutingType = 'smartRoutingType';
    case GeolocationLatitude = 'geolocationLatitude';
    case GeolocationLongitude = 'geolocationLongitude';
    case LatencyZone = 'latencyZone';
}
