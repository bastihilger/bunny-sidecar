<?php

declare(strict_types=1);

namespace BunnySidecar\BunnySidecar\Tests;

use BunnySidecar\BunnySidecar\BunnySidecarServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            BunnySidecarServiceProvider::class,
        ];
    }
}
