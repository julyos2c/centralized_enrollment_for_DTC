<?php

namespace Database\Seeders\Concerns;

use RuntimeException;

/**
 * For seeders that insert sample/demo data. They refuse to run outside the
 * local and testing environments, even when invoked directly with --class.
 */
trait DevelopmentOnly
{
    protected function ensureDevelopmentEnvironment(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException(static::class . ' inserts sample data and only runs in the local or testing environment (current: ' . app()->environment() . '). Nothing was changed.');
        }
    }
}
