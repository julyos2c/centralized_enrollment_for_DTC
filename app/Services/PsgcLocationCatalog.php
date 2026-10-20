<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;

/** Read-only PSGC snapshot used by both the public picker and submission validation. */
class PsgcLocationCatalog
{
    private static ?array $data = null;

    private function rows(string $key): Collection
    {
        if (self::$data === null) {
            self::$data = [];
        }

        if (!array_key_exists($key, self::$data)) {
            self::$data[$key] = json_decode(
                File::get(resource_path('data/psgc/' . $key . '.json')),
                true,
                512,
                JSON_THROW_ON_ERROR
            );
        }

        return collect(self::$data[$key]);
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
        if ($regionCode === '1300000000') {
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

    private function option(array $row, string $type = 'province'): array
    {
        return [
            'code' => $row['psgc_code'],
            'name' => $row['name'],
            'type' => $type,
        ];
    }
}
