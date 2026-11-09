<?php

namespace App\Services\Psgc;

use Illuminate\Support\Facades\File;
use RuntimeException;

/**
 * Turns the raw PSGC source files (regions, provinces, cities, barangays,
 * zip_codes) into the compact snapshot read by PsgcLocationCatalog:
 *
 *   regions.json, provinces.json, cities.json   same shape as before
 *   barangays/{region_code}.json                 {city_code: [[code, name], ...]}
 *   postal.json                                  {city_code: [{code, area}, ...]}
 *   meta.json                                    version, source, counts
 *
 * Manila's barangays belong to sub-municipal districts in PSGC; they are
 * listed under the City of Manila with the district in the name.
 */
class PsgcDatasetBuilder
{
    public const FILES = ['regions', 'provinces', 'cities', 'barangays', 'zip_codes'];

    private const NCR = '1300000000';

    /**
     * @param array<string, mixed> $source decoded source files keyed by FILES
     * @return array<string, string> relative path => file contents
     */
    public function build(array $source, string $sourceLabel, array $minimumCounts = []): array
    {
        foreach (self::FILES as $file) {
            if (!isset($source[$file]) || !is_array($source[$file])) {
                throw new RuntimeException("Source file {$file}.json is missing or not valid JSON.");
            }
        }

        $regions = array_map(fn ($r) => ['psgc_code' => $this->code($r), 'name' => $this->name($r)], $source['regions']);
        $provinces = array_map(fn ($p) => array_filter([
            'psgc_code' => $this->code($p),
            'name' => $this->name($p),
            'region_code' => $this->code($p, 'region_code'),
            'is_pseudo' => !empty($p['is_pseudo']) ? true : null,
        ], fn ($v) => $v !== null), $source['provinces']);

        $cities = [];
        $subMunicipalityParent = [];
        foreach ($source['cities'] as $c) {
            $code = $this->code($c);
            if (($c['geographic_level'] ?? null) === 'SubMun') {
                $subMunicipalityParent[$code] = ['parent' => substr($code, 0, 5) . '00000', 'name' => $this->name($c)];
                continue;
            }
            $cities[] = [
                'psgc_code' => $code,
                'name' => $this->name($c),
                'province_code' => $this->code($c, 'province_code'),
                'region_code' => $this->code($c, 'region_code'),
            ];
        }

        $this->assertIntegrity($regions, $provinces, $cities, $subMunicipalityParent);

        $cityRegion = array_column($cities, 'region_code', 'psgc_code');
        $barangays = [];
        $barangayCount = 0;
        foreach ($source['barangays'] as $b) {
            $cityCode = $this->code($b, 'city_code');
            $name = $this->name($b);
            if (isset($subMunicipalityParent[$cityCode])) {
                $name .= ' (' . $subMunicipalityParent[$cityCode]['name'] . ')';
                $cityCode = $subMunicipalityParent[$cityCode]['parent'];
            }
            if (!isset($cityRegion[$cityCode])) {
                throw new RuntimeException("Barangay {$this->code($b)} references unknown city {$cityCode}.");
            }
            $barangays[$cityRegion[$cityCode]][$cityCode][] = [$this->code($b), $name];
            $barangayCount++;
        }
        foreach ($barangays as &$byCity) {
            ksort($byCity);
            foreach ($byCity as &$list) {
                usort($list, fn ($a, $b) => strnatcasecmp($a[1], $b[1]));
            }
        }
        unset($byCity, $list);

        $counts = [
            'regions' => count($regions),
            'provinces' => count($provinces),
            'cities' => count($cities),
            'barangays' => $barangayCount,
        ];
        foreach ($minimumCounts as $key => $minimum) {
            if (($counts[$key] ?? 0) < $minimum) {
                throw new RuntimeException("Only {$counts[$key]} {$key} found (expected at least {$minimum}); the source looks incomplete.");
            }
        }

        $postal = (new PostalCodeMatcher())->match($source['zip_codes'], $cities, $provinces);
        $counts['postal_codes'] = array_sum(array_map('count', $postal));
        $counts['cities_with_postal_code'] = count($postal);

        $files = [
            'regions.json' => $this->json($regions),
            'provinces.json' => $this->json(array_values($provinces)),
            'cities.json' => $this->json($cities),
            'postal.json' => $this->json($postal, true),
        ];
        foreach ($barangays as $regionCode => $byCity) {
            $files["barangays/{$regionCode}.json"] = $this->json($byCity, true);
        }

        ksort($files);
        $version = substr(sha1(implode("\n", $files)), 0, 12);
        $files['meta.json'] = json_encode([
            'version' => $version,
            'source' => $sourceLabel,
            'generated_at' => now()->toIso8601String(),
            'counts' => $counts,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";

        return $files;
    }

    /**
     * Write files into $destination atomically: build in a sibling temp
     * directory, then swap it in, so readers never see a half-written set.
     */
    public function write(array $files, string $destination): void
    {
        $parent = dirname($destination);
        File::ensureDirectoryExists($parent);
        $temp = $parent . DIRECTORY_SEPARATOR . '.' . basename($destination) . '-' . bin2hex(random_bytes(4));
        $old = $temp . '-old';

        try {
            foreach ($files as $relative => $contents) {
                $path = $temp . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
                File::ensureDirectoryExists(dirname($path));
                if (File::put($path, $contents) === false) {
                    throw new RuntimeException("Could not write {$path}.");
                }
            }
            // Keep hand-written notes (README.md) that live beside the data.
            foreach (File::isDirectory($destination) ? File::glob($destination . DIRECTORY_SEPARATOR . '*.md') : [] as $note) {
                File::copy($note, $temp . DIRECTORY_SEPARATOR . basename($note));
            }

            if (File::isDirectory($destination) && !@rename($destination, $old)) {
                throw new RuntimeException("Could not replace {$destination}; check that the directory is writable.");
            }
            if (!@rename($temp, $destination)) {
                if (File::isDirectory($old)) {
                    @rename($old, $destination);
                }
                throw new RuntimeException("Could not move the new data into {$destination}.");
            }
            File::deleteDirectory($old);
        } finally {
            if (File::isDirectory($temp)) {
                File::deleteDirectory($temp);
            }
        }
    }

    private function assertIntegrity(array $regions, array $provinces, array $cities, array $subMunicipalities): void
    {
        $regionCodes = array_flip(array_column($regions, 'psgc_code'));
        $provinceCodes = array_flip(array_column($provinces, 'psgc_code'));
        $cityCodes = array_flip(array_column($cities, 'psgc_code'));

        foreach ($provinces as $p) {
            if (!isset($regionCodes[$p['region_code']])) {
                throw new RuntimeException("Province {$p['psgc_code']} references unknown region {$p['region_code']}.");
            }
        }
        foreach ($cities as $c) {
            if (!isset($regionCodes[$c['region_code']])) {
                throw new RuntimeException("City {$c['psgc_code']} references unknown region {$c['region_code']}.");
            }
            // NCR cities use the region code as their (pseudo) province.
            if (!isset($provinceCodes[$c['province_code']]) && $c['province_code'] !== self::NCR) {
                throw new RuntimeException("City {$c['psgc_code']} references unknown province {$c['province_code']}.");
            }
        }
        foreach ($subMunicipalities as $code => $sub) {
            if (!isset($cityCodes[$sub['parent']])) {
                throw new RuntimeException("Sub-municipality {$code} has no parent city {$sub['parent']}.");
            }
        }
    }

    private function code(array $row, string $key = 'psgc_code'): string
    {
        $code = (string) ($row[$key] ?? '');
        if (!preg_match('/^\d{10}$/', $code)) {
            throw new RuntimeException("Invalid PSGC {$key} '{$code}'.");
        }

        return $code;
    }

    private function name(array $row): string
    {
        $name = trim((string) ($row['name'] ?? ''));
        if ($name === '') {
            throw new RuntimeException('A PSGC record has no name.');
        }

        return $name;
    }

    private function json(array $data, bool $object = false): string
    {
        $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
        if ($object && $data === []) {
            return "{}\n";
        }

        return json_encode($data, $flags) . "\n";
    }
}
