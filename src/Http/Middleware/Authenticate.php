<?php

declare(strict_types=1);

namespace BunnySidecar\BunnySidecar\Http\Middleware;

use BunnySidecar\BunnySidecar\BunnySidecar;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Requires a panel user on the package's own guard and makes that guard the
 * default for the rest of the request, so auth() and Gate resolve panel users.
 */
class Authenticate
{
    public function __construct(private BunnySidecar $sidecar) {}

    /**
     * @param  Closure(Request): Response  $next
     *
     * @throws AuthenticationException
     */
    public function handle(Request $request, Closure $next): Response
    {
        $guard = $this->sidecar->guard();

        if (! Auth::guard($guard)->check()) {
            throw new AuthenticationException(
                'Unauthenticated.',
                [$guard],
                $request->expectsJson() ? null : $this->sidecar->loginUrl(),
            );
        }

        Auth::shouldUse($guard);

        return $next($request);
    }
}
