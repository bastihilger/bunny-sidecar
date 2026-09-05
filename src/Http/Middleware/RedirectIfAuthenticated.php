<?php

declare(strict_types=1);

namespace BunnySidecar\BunnySidecar\Http\Middleware;

use BunnySidecar\BunnySidecar\BunnySidecar;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sends already authenticated panel users away from the guest pages.
 */
class RedirectIfAuthenticated
{
    public function __construct(private BunnySidecar $sidecar) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->sidecar->auth()->check()) {
            return redirect()->to($this->sidecar->homeUrl());
        }

        return $next($request);
    }
}
