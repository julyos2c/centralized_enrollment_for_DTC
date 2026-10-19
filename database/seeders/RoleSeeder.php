<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = ['admin', 'registrar', 'cashier', 'new_applicant', 'student', 'alumni'];

        foreach ($roles as $role) {
            Role::firstOrCreate(['name' => $role]);
        }

        // Create default admin account
        $admin = \App\Models\User::firstOrCreate(
            ['email' => 'admin@dtc.edu.ph'],
            [
                'name'     => 'Administrator',
                'password' => bcrypt('admin123'),
            ]
        );
        $admin->assignRole('admin');
    }
}