<?php

namespace Database\Seeders;

use Database\Seeders\Concerns\DevelopmentOnly;
use Illuminate\Database\Seeder;

/**
 * Placeholder for fake demo records (students, applications, payments).
 * Refuses to run in production; keep demo data out of real databases.
 */
class DemoDataSeeder extends Seeder
{
    use DevelopmentOnly;

    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $this->ensureDevelopmentEnvironment();
    }
}
