<?php

declare(strict_types=1);

namespace BunnySidecar\BunnySidecar\Console\Commands;

use Illuminate\Console\Command;

class BunnySidecarCommand extends Command
{
    /**
     * The command signature.
     */
    protected $signature = 'bunny-sidecar:placeholder';

    /**
     * The command description.
     */
    protected $description = 'Placeholder Artisan command shipped by the package bunny-sidecar.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->line('BunnySidecar placeholder command executed.');

        return self::SUCCESS;
    }
}
