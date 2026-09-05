<?php

declare(strict_types=1);

namespace BunnySidecar\BunnySidecar\Http\Controllers;

use BunnySidecar\BunnySidecar\BunnySidecar;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Serves the panel's static assets (precompiled stylesheet, favicon) straight
 * from the package, so no asset publishing or frontend build is needed in
 * the host application.
 */
class AssetController
{
    public function __invoke(BunnySidecar $sidecar, string $asset): BinaryFileResponse
    {
        abort_unless(array_key_exists($asset, BunnySidecar::ASSETS), 404);

        return response()->file($sidecar->assetPath($asset), [
            'Content-Type' => BunnySidecar::ASSETS[$asset],
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }
}
