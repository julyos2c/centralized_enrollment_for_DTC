<?php

namespace App\Console\Commands;

use App\Services\Psgc\PsgcDatasetBuilder;
use App\Services\PsgcLocationCatalog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

/**
 * Refresh the PSGC address snapshot. Nothing is replaced unless every
 * source file downloads, parses, and passes integrity checks, so a source
 * outage leaves the current data (or the bundled snapshot) in use.
 */
class SyncPsgcData extends Command
{
    protected $signature = 'psgc:sync
        {--source= : Base URL or local directory holding regions/provinces/cities/barangays/zip_codes .json (defaults to config psgc.source_url)}
        {--bundled : Write to resources/data/psgc (for developers to commit) instead of storage/app/psgc}
        {--dry-run : Download and validate only; write nothing}';

    protected $description = 'Download, validate, and install the PSGC address and postal code snapshot.';

    public function handle(PsgcDatasetBuilder $builder): int
    {
        $source = rtrim((string) ($this->option('source') ?: config('psgc.source_url')), '/\\');
        $destination = $this->option('bundled') ? PsgcLocationCatalog::bundledPath() : PsgcLocationCatalog::storagePath();

        try {
            $raw = [];
            foreach (PsgcDatasetBuilder::FILES as $file) {
                $this->line("Reading {$file}.json …");
                $raw[$file] = $this->read($source, $file);
            }
            $files = $builder->build($raw, $source, config('psgc.minimum_counts', []));
        } catch (\Throwable $exception) {
            $this->error('PSGC sync failed: ' . $exception->getMessage());
            $this->warn('No files were changed. The application keeps using its current PSGC data.');

            return self::FAILURE;
        }

        $meta = json_decode($files['meta.json'], true);
        $this->table(['Item', 'Count'], collect($meta['counts'])->map(fn ($count, $key) => [str_replace('_', ' ', $key), number_format($count)])->values()->all());

        if ($this->option('dry-run')) {
            $this->info("Validated version {$meta['version']}; nothing written (dry run).");

            return self::SUCCESS;
        }

        try {
            $builder->write($files, $destination);
        } catch (\Throwable $exception) {
            $this->error('Could not install the PSGC data: ' . $exception->getMessage());
            $this->warn('The previous PSGC data is still in use.');

            return self::FAILURE;
        }

        PsgcLocationCatalog::flush();
        $this->info("Installed PSGC data version {$meta['version']} in {$destination}.");

        return self::SUCCESS;
    }

    private function read(string $source, string $file): array
    {
        if (preg_match('#^https?://#i', $source)) {
            $response = Http::timeout(config('psgc.source_timeout', 120))
                ->retry(3, 2000)
                ->acceptJson()
                ->get("{$source}/{$file}.json");
            $response->throw();
            $body = $response->body();
        } else {
            $path = $source . DIRECTORY_SEPARATOR . $file . '.json';
            if (!File::exists($path)) {
                throw new \RuntimeException("{$path} does not exist.");
            }
            $body = File::get($path);
        }

        $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($data)) {
            throw new \RuntimeException("{$file}.json does not contain a JSON array or object.");
        }

        return $data;
    }
}
