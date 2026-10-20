<?php

namespace Database\Seeders;

use App\Models\User;
use Database\Seeders\Concerns\DevelopmentOnly;
use Illuminate\Database\Seeder;

/**
 * Local/testing convenience data. Never runs in production.
 *
 * - Sample programs and subjects (SubjectSeeder) — NOT an approved curriculum.
 * - An optional local administrator, only when SEED_ADMIN_EMAIL and
 *   SEED_ADMIN_PASSWORD are set in the local .env. An existing account with
 *   that email is never modified (password, name, and status are kept).
 */
class DevelopmentSeeder extends Seeder
{
    use DevelopmentOnly;

    public function run(): void
    {
        $this->ensureDevelopmentEnvironment();

        $this->call(SubjectSeeder::class);

        $email = env('SEED_ADMIN_EMAIL');
        $password = env('SEED_ADMIN_PASSWORD');
        if (! $email || ! $password) {
            $this->command?->info('Local admin: set SEED_ADMIN_EMAIL and SEED_ADMIN_PASSWORD in .env to create one; skipped.');
            return;
        }

        $admin = User::firstOrCreate(
            ['email' => $email],
            ['name' => 'Local Administrator', 'password' => bcrypt($password), 'status' => 'active']
        );
        if (! $admin->hasRole('admin')) {
            $admin->assignRole('admin');
        }
        $this->command?->info($admin->wasRecentlyCreated ? "Local admin created: $email" : "Local admin $email already exists; left unchanged.");
    }
}
