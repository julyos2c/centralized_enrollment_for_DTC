<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

/**
 * Read-only PSGC snapshot used by both the public picker and submission validation.
 *
 * Data is read from local files only. A copy installed by `psgc:sync` in
 * storage/app/psgc is preferred while it is complete; otherwise the
 * snapshot bundled in resources/data/psgc is used, so the picker keeps
 * working even if a refresh never ran or was interrupted.
 */
class PsgcLocationCatalog
{
    private const NCR = '1300000000';

    private const CORE_FILES = ['regions', 'provinces', 'cities', 'postal', 'meta'];

    private static ?array $data = null;

    private static ?string $path = null;

    public static function bundledPath(): string
    {
        return resource_path('data/psgc');
    }

    public static function storagePath(): string
    {
        return storage_path('app/psgc');
    }

    /** Forget cached data (after a sync, or between tests). */
    public static function flush(): void
    {
        self::$data = null;
        self::$path = null;
    }

    /** Directory currently serving PSGC data. */
    public function dataPath(): string
    {
        if (self::$path === null) {
            self::$path = $this->isComplete(self::storagePath()) ? self::storagePath() : self::bundledPath();
        }

        return self::$path;
    }

    /** Short identifier of the active data, used to version API URLs. */
    public function version(): string
    {
        return (string) ($this->file('meta')['version'] ?? 'bundled');
    }

    public function meta(): array
    {
        return $this->file('meta') + ['path' => $this->dataPath()];
    }

    private function isComplete(string $directory): bool
    {
        foreach (self::CORE_FILES as $file) {
            if (!is_file($directory . DIRECTORY_SEPARATOR . $file . '.json')) {
                return false;
            }
        }
        $meta = json_decode((string) @file_get_contents($directory . DIRECTORY_SEPARATOR . 'meta.json'), true);

        return is_array($meta) && !empty($meta['version']);
    }

    private function file(string $key): array
    {
        self::$data ??= [];
        if (!array_key_exists($key, self::$data)) {
            $path = $this->dataPath() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $key) . '.json';
            if (!is_file($path)) {
                // Optional files (a region without barangays) read as empty.
                return self::$data[$key] = [];
            }
            try {
                self::$data[$key] = json_decode(File::get($path), true, 512, JSON_THROW_ON_ERROR);
            } catch (\Throwable $exception) {
                Log::error('PSGC data file could not be read.', ['file' => $path, 'exception' => get_class($exception)]);
                if ($this->dataPath() !== self::bundledPath()) {
                    // A damaged synced copy: fall back to the bundled snapshot.
                    self::flush();
                    self::$path = self::bundledPath();

                    return $this->file($key);
                }
                throw $exception;
            }
        }

        return self::$data[$key];
    }

    private function rows(string $key): Collection
    {
        return collect($this->file($key));
    }

    public function regions(): array
    {
        return $this->rows('regions')->map(fn ($row) => $this->option($row))->values()->all();
    }

    /** Return regular provinces and independent city options for a region. */
    public function areas(string $regionCode): array
    {
        $region = $this->rows('regions')->firstWhere('psgc_code', $regionCode);
        if (!$region) {
            return [];
        }

        $provinces = $this->rows('provinces')->where('region_code', $regionCode);
        $independentProvinceCodes = $provinces->where('is_pseudo', true)
            ->where('psgc_code', '!=', $regionCode)->pluck('psgc_code')->all();

        $areas = $provinces->reject(fn ($province) => ($province['is_pseudo'] ?? false))
            ->map(fn ($province) => $this->option($province, 'province'));

        $independentCities = $this->rows('cities')
            ->where('region_code', $regionCode)
            ->whereIn('province_code', $independentProvinceCodes)
            ->map(fn ($city) => $this->option($city, 'city'));

        // NCR uses a pseudo-province whose code equals the region code.
        if ($regionCode === self::NCR) {
            $independentCities = $this->rows('cities')->where('region_code', $regionCode)
                ->where('province_code', $regionCode)
                ->map(fn ($city) => $this->option($city, 'city'));
        }

        return $areas->concat($independentCities)->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)->values()->all();
    }

    public function cities(string $areaCode): array
    {
        $province = $this->rows('provinces')->firstWhere('psgc_code', $areaCode);
        if (!$province || ($province['is_pseudo'] ?? false)) {
            return [];
        }

        return $this->rows('cities')->where('province_code', $areaCode)
            ->map(fn ($city) => $this->option($city, 'city'))
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)->values()->all();
    }

    public function region(string $code): ?array
    {
        return $this->rows('regions')->firstWhere('psgc_code', $code);
    }

    public function area(string $code, string $regionCode): ?array
    {
        return collect($this->areas($regionCode))->firstWhere('code', $code);
    }

    public function city(string $code, string $areaCode): ?array
    {
        return collect($this->cities($areaCode))->firstWhere('code', $code);
    }

    /** Any city or municipality by code, regardless of its parent. */
    public function cityByCode(string $code): ?array
    {
        return $this->rows('cities')->firstWhere('psgc_code', $code);
    }

    /** @return array<int, array{code: string, name: string}> */
    public function barangays(string $cityCode): array
    {
        $city = $this->cityByCode($cityCode);
        if (!$city) {
            return [];
        }
        $byCity = $this->file('barangays/' . $city['region_code']);

        return array_map(fn ($row) => ['code' => $row[0], 'name' => $row[1]], $byCity[$cityCode] ?? []);
    }

    /** Canonical barangay name for free text, or null when it is not on the list. */
    public function barangayName(string $cityCode, string $name): ?string
    {
        $wanted = $this->comparable($name);
        foreach ($this->barangays($cityCode) as $barangay) {
            if ($this->comparable($barangay['name']) === $wanted) {
                return $barangay['name'];
            }
        }

        return null;
    }

    /** @return array<int, array{code: string, area: string}> */
    public function postalCodes(string $cityCode): array
    {
        return $this->file('postal')[$cityCode] ?? [];
    }

    private function comparable(string $value): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $value)));
    }

    private function option(array $row, string $type = 'province'): array
    {
        return [
            'code' => $row['psgc_code'],
            'name' => $row['name'],
            'type' => $type,
        ];
    }
}
