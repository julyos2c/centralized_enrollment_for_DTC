<?php

namespace Tests;

use Spatie\Permission\Models\Role;

trait SeedsRoles
{
    protected function seedRoles(): void
    {
        foreach (['admin', 'registrar', 'cashier', 'new_applicant', 'student', 'alumni'] as $role) {
            Role::firstOrCreate(['name' => $role]);
        }
    }

    protected function makeUserWithRole(string $role, array $attributes = [])
    {
        $user = \App\Models\User::factory()->create($attributes);
        $user->assignRole($role);

        return $user;
    }
}
