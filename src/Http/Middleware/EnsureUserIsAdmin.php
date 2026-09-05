<?php

declare(strict_types=1);

namespace BunnySidecar\BunnySidecar\Http\Middleware;

use BunnySidecar\BunnySidecar\BunnySidecar;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdmin
{
    public function __construct(private BunnySidecar $sidecar) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($this->sidecar->user()?->isAdmin() === true, 403);

        return $next($request);
    }
}
