<?php

declare(strict_types=1);

namespace BunnySidecar\BunnySidecar\Http\Controllers;

use BunnySidecar\BunnySidecar\BunnySidecar;
use Illuminate\Http\RedirectResponse;

class HomeController
{
    public function __invoke(BunnySidecar $sidecar): RedirectResponse
    {
        return redirect()->to($sidecar->auth()->check() ? $sidecar->homeUrl() : $sidecar->loginUrl());
    }
}
