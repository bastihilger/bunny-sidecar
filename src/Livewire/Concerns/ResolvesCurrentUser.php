<?php

declare(strict_types=1);

namespace BunnySidecar\BunnySidecar\Livewire\Concerns;

use BunnySidecar\BunnySidecar\BunnySidecar;
use BunnySidecar\BunnySidecar\Models\User;

/**
 * Resolves the panel user through the package guard, independent of the
 * host application's default guard.
 */
trait ResolvesCurrentUser
{
    protected function currentUser(): User
    {
        return app(BunnySidecar::class)->currentUser();
    }
}
