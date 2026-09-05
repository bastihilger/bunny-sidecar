<?php

declare(strict_types=1);

namespace BunnySidecar\BunnySidecar\Console\Commands;

use BunnySidecar\BunnySidecar\Enums\UserRole;
use BunnySidecar\BunnySidecar\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

class CreateAdminCommand extends Command
{
    protected $signature = 'bunny-sidecar:create-admin {--name=} {--email=} {--password=}';

    protected $description = 'Create a Bunny Sidecar admin user (everyone else joins by invitation)';

    public function handle(): int
    {
        $name = $this->option('name') ?? text(label: 'Name', required: true);
        $email = $this->option('email') ?? text(label: 'Email address', required: true);
        $password = $this->option('password') ?? password(label: 'Password', required: true);

        $validator = Validator::make(
            ['name' => $name, 'email' => $email, 'password' => $password],
            [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class, 'email')],
                'password' => ['required', 'string', Password::default()],
            ],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        $user = User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'role' => UserRole::Admin,
        ]);

        $this->components->info("Admin user [{$user->email}] created.");

        return self::SUCCESS;
    }
}
