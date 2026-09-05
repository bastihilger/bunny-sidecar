<?php

declare(strict_types=1);

namespace BunnySidecar\BunnySidecar\Console\Commands;

use BunnySidecar\BunnySidecar\BunnySidecar;
use BunnySidecar\BunnySidecar\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

use function Laravel\Prompts\confirm;

class InstallCommand extends Command
{
    protected $signature = 'bunny-sidecar:install
        {--no-migrate : Publish the migrations without running them}
        {--no-admin : Skip creating the first admin user}';

    protected $description = 'Install Bunny Sidecar: publish config and migrations, migrate, and create the first admin';

    public function handle(BunnySidecar $sidecar): int
    {
        $this->components->info('Installing Bunny Sidecar...');

        $this->callSilently('vendor:publish', ['--tag' => 'bunny-sidecar-config']);
        $this->components->task('Publishing configuration');

        $this->callSilently('vendor:publish', ['--tag' => 'bunny-sidecar-migrations']);
        $this->components->task('Publishing migrations');

        if (! $this->option('no-migrate') && confirm('Run the database migrations now?', default: true)) {
            $this->call('migrate');
        }

        if (! $this->option('no-admin') && Schema::hasTable('bunny_sidecar_users') && ! User::query()->exists()) {
            if (confirm('Create the first admin user now?', default: true)) {
                $this->call('bunny-sidecar:create-admin');
            }
        }

        $this->newLine();
        $this->components->info('Bunny Sidecar is installed. Log in at '.$sidecar->loginUrl());
        $this->components->bulletList([
            'Configure a mailer so invitation and password reset mails are delivered.',
            'Create further admins any time with: php artisan bunny-sidecar:create-admin',
        ]);

        return self::SUCCESS;
    }
}
