<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Backwards-compatible alias for RolePermissionSeeder.
 *
 * This seeder used to also create a default administrator with a hard-coded
 * password. That account creation was removed: administrators must be
 * provisioned through a controlled process (or, locally, DevelopmentSeeder
 * with SEED_ADMIN_EMAIL / SEED_ADMIN_PASSWORD).
 */
class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RolePermissionSeeder::class);
    }
}
