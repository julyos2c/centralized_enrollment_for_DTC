<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Mandatory system reference data: the application roles.
 *
 * Authorization in this application is role-based (route `role:` middleware
 * and hasRole()/hasAnyRole() checks); no Spatie permissions are used, so none
 * are created here. Production-safe and idempotent: it only creates missing
 * roles and never deletes, renames, or reassigns roles, and never touches
 * users or passwords.
 */
class RolePermissionSeeder extends Seeder
{
    /** Every role name the application code references. */
    public const ROLES = ['admin', 'registrar', 'cashier', 'new_applicant', 'student', 'alumni'];

    public const GUARD = 'web';

    public function run(): void
    {
        $created = [];

        foreach (self::ROLES as $name) {
            $role = Role::firstOrCreate(['name' => $name, 'guard_name' => self::GUARD]);
            if ($role->wasRecentlyCreated) {
                $created[] = $name;
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->command?->info($created
            ? 'Roles created: ' . implode(', ', $created)
            : 'Roles: all ' . count(self::ROLES) . ' already present, nothing changed.');
    }
}
