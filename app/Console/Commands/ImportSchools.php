<?php

namespace App\Console\Commands;

use App\Services\Schools\SchoolImporter;
use Illuminate\Console\Command;

class ImportSchools extends Command
{
    protected $signature = 'schools:import
        {file? : CSV file in DepEd column conventions (defaults to the bundled Bohol school list)}
        {--source= : Label stored with new rows (defaults to the file name)}
        {--replace : Hide imported schools that are not in this file (staff-added schools and all records are kept)}';

    protected $description = 'Add schools from a CSV to the school reference list (idempotent; never deletes or overwrites staff edits).';

    public function handle(SchoolImporter $importer): int
    {
        $path = $this->argument('file') ?: resource_path(config('schools.bundled_file'));
        $source = $this->option('source') ?: pathinfo($path, PATHINFO_FILENAME);

        try {
            $result = $importer->import($path, substr($source, 0, 60), (bool) $this->option('replace'));
        } catch (\Throwable $exception) {
            $this->error('Import failed: ' . $exception->getMessage());
            $this->warn('No schools were changed.');

            return self::FAILURE;
        }

        $this->table(['Added', 'Filled gaps', 'Unchanged', 'Skipped', 'Location not matched to a city', 'Hidden (not in file)'], [[
            $result['added'], $result['updated'], $result['unchanged'], $result['skipped'], $result['unmatched_location'], $result['hidden'],
        ]]);
        foreach (array_slice($result['errors'], 0, 20) as $error) {
            $this->warn($error);
        }

        return self::SUCCESS;
    }
}
