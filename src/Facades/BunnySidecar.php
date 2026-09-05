<?php

declare(strict_types=1);

namespace BunnySidecar\BunnySidecar\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \BunnySidecar\BunnySidecar\BunnySidecar
 */
class BunnySidecar extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \BunnySidecar\BunnySidecar\BunnySidecar::class;
    }
}
