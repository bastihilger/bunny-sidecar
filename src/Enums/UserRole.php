<?php

declare(strict_types=1);

namespace BunnySidecar\BunnySidecar\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Member = 'member';

    public function label(): string
    {
        return match ($this) {
            self::Admin => __('Admin'),
            self::Member => __('Member'),
        };
    }
}
