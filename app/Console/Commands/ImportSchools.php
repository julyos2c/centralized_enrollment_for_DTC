<?php

namespace App\Console\Commands;

use App\Services\Schools\SchoolImporter;
use Illuminate\Console\Command;

class ImportSchools extends Command
{
    public const BUNDLED = 'data/schools/deped-shs-list-2018.csv';

    protected $signature = 'schools:import
        {file? : CSV file in DepEd column conventions (defaults to the bundled DepEd SHS list)}
        {--source= : Label stored with new rows (defaults to the file name)}';

    protected $description = 'Add schools from a DepEd-format CSV to the school reference list (idempotent; never deletes or overwrites edits).';

    public function handle(SchoolImporter $importer): int
    {
        $path = $this->argument('file') ?: resource_path(self::BUNDLED);
        $source = $this->option('source') ?: pathinfo($path, PATHINFO_FILENAME);

        try {
            $result = $importer->import($path, substr($source, 0, 60));
        } catch (\Throwable $exception) {
            $this->error('Import failed: ' . $exception->getMessage());
            $this->warn('No schools were changed.');

            return self::FAILURE;
        }

        $this->table(['Added', 'Filled gaps', 'Unchanged', 'Skipped', 'Location not matched to a city'], [[
            $result['added'], $result['updated'], $result['unchanged'], $result['skipped'], $result['unmatched_location'],
        ]]);
        foreach (array_slice($result['errors'], 0, 20) as $error) {
            $this->warn($error);
        }

        return self::SUCCESS;
    }
}
