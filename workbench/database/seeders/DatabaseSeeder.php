<?php

namespace Workbench\Database\Seeders;

use BunnySidecar\BunnySidecar\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed a panel admin for the workbench: admin@example.com / password.
     */
    public function run(): void
    {
        User::factory()->admin()->create([
            'name' => 'Workbench Admin',
            'email' => 'admin@example.com',
        ]);
    }
}
