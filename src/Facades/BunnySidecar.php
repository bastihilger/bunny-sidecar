<?php

declare(strict_types=1);

namespace BunnySidecar\BunnySidecar\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static string guard()
 * @method static \Illuminate\Contracts\Auth\StatefulGuard auth()
 * @method static \BunnySidecar\BunnySidecar\Models\User|null user()
 * @method static \BunnySidecar\BunnySidecar\Models\User currentUser()
 * @method static string name()
 * @method static string path()
 * @method static string loginUrl()
 * @method static string homeUrl()
 * @method static string stylesheetUrl()
 * @method static string faviconUrl()
 * @method static string assetUrl(string $asset)
 *
 * @see \BunnySidecar\BunnySidecar\BunnySidecar
 */
class BunnySidecar extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \BunnySidecar\BunnySidecar\BunnySidecar::class;
    }
}
