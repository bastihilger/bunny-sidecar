<?php

declare(strict_types=1);

namespace BunnySidecar\BunnySidecar\Http\Controllers;

use BunnySidecar\BunnySidecar\BunnySidecar;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Serves the precompiled panel stylesheet straight from the package, so no
 * asset publishing or frontend build is needed in the host application.
 */
class StylesheetController
{
    public function __invoke(BunnySidecar $sidecar): BinaryFileResponse
    {
        return response()->file($sidecar->stylesheetPath(), [
            'Content-Type' => 'text/css; charset=utf-8',
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }
}
