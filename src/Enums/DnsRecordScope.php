<?php

declare(strict_types=1);

namespace BunnySidecar\BunnySidecar\Enums;

/**
 * Where within a zone a record lives: at the zone root ("@" / empty name)
 * or on any subdomain. Grants are segmented by this scope.
 */
enum DnsRecordScope: string
{
    case Root = 'root';
    case Subdomain = 'subdomain';

    public static function fromName(string $name): self
    {
        $name = trim($name);

        return $name === '' || $name === '@' ? self::Root : self::Subdomain;
    }

    public function label(): string
    {
        return match ($this) {
            self::Root => __('Zone root (@)'),
            self::Subdomain => __('Subdomains'),
        };
    }
}
