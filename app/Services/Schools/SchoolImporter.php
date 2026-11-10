<?php

namespace App\Services\Schools;

use App\Models\School;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Imports school lists in DepEd's CSV conventions (the SHS list bundled in
 * resources/data/schools, or a masterlist obtained from DepEd EMISD/FOI).
 *
 * Rows are matched on the DepEd School ID. New schools are added; existing
 * ones only gain values that are still empty, so staff edits are never
 * overwritten and nothing is deleted.
 */
class SchoolImporter
{
    private const HEADERS = [
        'deped_school_id' => ['school_id', 'school id', 'beis school id', 'schoolid', 'deped school id'],
        'name' => ['school_name', 'school name', 'name'],
        'region' => ['region'],
        'province' => ['province'],
        'municipality' => ['municipality', 'city/municipality', 'city / municipality', 'city', 'municipality/city'],
        'barangay' => ['barangay'],
        'street_address' => ['street_address', 'street address', 'address'],
        'sector' => ['sector', 'classification'],
        'offerings' => ['program_offerings', 'program offerings', 'offerings'],
    ];

    public const OFFERINGS = ['STEM', 'ABM', 'HUMSS', 'GAS', 'TVL', 'Sports', 'Arts and Design'];

    public function __construct(private SchoolLocationResolver $locations)
    {
    }

    /** @return array{added: int, updated: int, unchanged: int, skipped: int, unmatched_location: int, errors: array} */
    public function import(string $path, string $source): array
    {
        $handle = @fopen($path, 'r');
        if (!$handle) {
            throw new RuntimeException("Cannot open {$path}.");
        }

        $header = fgetcsv($handle);
        if (!$header) {
            throw new RuntimeException('The file is empty.');
        }
        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header[0]);
        $columns = $this->mapHeader($header);
        if (!isset($columns['name'])) {
            throw new RuntimeException('The file needs a "school_name" (or "School Name") column.');
        }

        $result = ['added' => 0, 'updated' => 0, 'unchanged' => 0, 'skipped' => 0, 'unmatched_location' => 0, 'errors' => []];
        $line = 1;

        DB::transaction(function () use ($handle, $columns, $source, &$result, &$line) {
            while (($row = fgetcsv($handle)) !== false) {
                $line++;
                if ($row === [null] || count(array_filter($row, fn ($v) => trim((string) $v) !== '')) === 0) {
                    continue;
                }
                $value = fn (string $key) => isset($columns[$key]) ? trim((string) ($row[$columns[$key]] ?? '')) : '';

                $name = preg_replace('/\s+/', ' ', $value('name'));
                $schoolId = $value('deped_school_id');
                if ($name === '' || mb_strlen($name) > 255) {
                    $result['skipped']++;
                    $result['errors'][] = "Line {$line}: missing or too long school name.";
                    continue;
                }
                if ($schoolId !== '' && !preg_match('/^\d{6}$/', $schoolId)) {
                    $result['skipped']++;
                    $result['errors'][] = "Line {$line}: school ID '{$schoolId}' is not a 6-digit DepEd School ID.";
                    continue;
                }

                $location = $this->locations->resolve($value('region'), $value('province'), $value('municipality'));
                if (!$location['city_code']) {
                    $result['unmatched_location']++;
                }
                $data = array_filter([
                    'name' => $name,
                    'sector' => $this->sector($value('sector')),
                    'region_code' => $location['region_code'],
                    'province_code' => $location['province_code'],
                    'city_code' => $location['city_code'],
                    'barangay' => $value('barangay') ?: null,
                    'street_address' => $value('street_address') ?: null,
                    'source_location' => implode(', ', array_filter([$value('municipality'), $value('province'), $value('region')])) ?: null,
                    'offerings' => $this->offerings($value('offerings')) ?: null,
                ], fn ($v) => $v !== null);

                $existing = $schoolId !== '' ? School::where('deped_school_id', $schoolId)->first() : null;
                if (!$existing) {
                    School::create($data + ['deped_school_id' => $schoolId ?: null, 'source' => $source, 'is_active' => true]);
                    $result['added']++;
                    continue;
                }

                // Fill gaps only; never overwrite what staff (or an earlier import) recorded.
                $fill = array_filter($data, fn ($v, $k) => blank($existing->{$k}), ARRAY_FILTER_USE_BOTH);
                if ($fill) {
                    $existing->update($fill);
                    $result['updated']++;
                } else {
                    $result['unchanged']++;
                }
            }
        });
        fclose($handle);

        return $result;
    }

    private function mapHeader(array $header): array
    {
        $columns = [];
        foreach ($header as $index => $label) {
            $label = strtolower(trim(preg_replace('/\s+/', ' ', (string) $label)));
            foreach (self::HEADERS as $key => $aliases) {
                if (!isset($columns[$key]) && in_array($label, $aliases, true)) {
                    $columns[$key] = $index;
                }
            }
        }

        return $columns;
    }

    private function sector(string $value): ?string
    {
        $value = strtolower($value);

        return match (true) {
            $value === '' => null,
            str_contains($value, 'luc') || str_contains($value, 'local univ') => 'luc',
            str_contains($value, 'suc') || str_contains($value, 'state univ') => 'suc',
            str_contains($value, 'private') => 'private',
            str_contains($value, 'public') || str_contains($value, 'deped') => 'public',
            default => null,
        };
    }

    private function offerings(string $value): array
    {
        $found = [];
        foreach (preg_split('/[|,;\/]+/', $value) as $token) {
            foreach (self::OFFERINGS as $offering) {
                if (strcasecmp(trim($token), $offering) === 0 && !in_array($offering, $found, true)) {
                    $found[] = $offering;
                }
            }
        }

        return $found;
    }
}
