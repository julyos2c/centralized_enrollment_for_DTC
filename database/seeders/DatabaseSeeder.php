<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Production-safe by design. Every seeder called unconditionally here is
     * idempotent and insert-only: it never truncates, deletes, or overwrites
     * existing records, and never creates users or passwords.
     *
     * Sample data (DevelopmentSeeder) is added only in local/testing.
     *
     * @return void
     */
    public function run()
    {
        $this->call([
            RolePermissionSeeder::class,        // mandatory: application roles
            SystemSettingsSeeder::class,        // mandatory: operational setting defaults (missing keys only)
            AcademicConfigurationSeeder::class, // optional: approved data file only; no-op without it
        ]);

        if (app()->environment(['local', 'testing'])) {
            $this->call(DevelopmentSeeder::class);
        }
    }
}
