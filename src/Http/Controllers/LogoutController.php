<?php

declare(strict_types=1);

namespace BunnySidecar\BunnySidecar\Http\Controllers;

use BunnySidecar\BunnySidecar\BunnySidecar;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LogoutController
{
    /**
     * Logs the panel user out of the panel guard only. The session itself is
     * kept, so a host application login sharing the cookie is not affected.
     */
    public function __invoke(Request $request, BunnySidecar $sidecar): RedirectResponse
    {
        $sidecar->auth()->logout();

        $request->session()->regenerateToken();

        return redirect()->to($sidecar->loginUrl());
    }
}
