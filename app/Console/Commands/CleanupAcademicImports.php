<?php

namespace App\Console\Commands;

use App\Models\AcademicImportBatch;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CleanupAcademicImports extends Command
{
    protected $signature = 'academic-imports:cleanup';
    protected $description = 'Remove expired private academic import files';

    public function handle(): int
    {
        AcademicImportBatch::where('status', 'previewed')->where('expires_at', '<=', now())->chunkById(100, function ($batches) {
            foreach ($batches as $batch) {
                Storage::disk('local')->delete(array_filter([$batch->source_path, $batch->report_path]));
                $batch->update(['status' => 'expired', 'source_path' => '', 'report_path' => null, 'completed_at' => now()]);
            }
        });
        return self::SUCCESS;
    }
}
