<?php

namespace App\Support;

use App\Services\PsgcLocationCatalog;

/**
 * Configuration for the PSGC address picker (public/js/address-cascade.js).
 *
 * Paths are relative to the host (with any sub-folder base path), so the
 * same markup works on localhost, behind the production HTTPS domain, and
 * through Cloudflare. ":code" is replaced by the browser.
 */
class AddressLookup
{
    public static function config(): array
    {
        $placeholder = '0000000000';
        // route(..., false) drops a sub-folder base path; add it back.
        $base = request()->getBaseUrl();
        $path = fn (string $name, array $params = []) => $base . str_replace($placeholder, ':code', route($name, $params, false));

        return [
            'version' => app(PsgcLocationCatalog::class)->version(),
            'regions' => $path('public.application.locations.regions'),
            'areas' => $path('public.application.locations.areas', ['region' => $placeholder]),
            'cities' => $path('public.application.locations.cities', ['province' => $placeholder]),
            'city' => $path('public.application.locations.city', ['city' => $placeholder]),
        ];
    }

    /**
     * Validation rules for a region / province / city code triple with the
     * given field names, checking that each code belongs to its parent.
     */
    public static function rules(array $input, string $region, string $province, string $city): array
    {
        $catalog = app(PsgcLocationCatalog::class);
        $regionCode = (string) ($input[$region] ?? '');
        $provinceCode = (string) ($input[$province] ?? '');
        $areas = $regionCode !== '' ? $catalog->areas($regionCode) : [];
        $cities = $provinceCode !== ''
            ? $catalog->cities($provinceCode)
            : array_values(array_filter($areas, fn ($a) => $a['type'] === 'city'));

        return [
            $region => ['nullable', 'string', \Illuminate\Validation\Rule::in(array_column($catalog->regions(), 'code'))],
            $province => ['nullable', 'string', \Illuminate\Validation\Rule::in(array_column(array_filter($areas, fn ($a) => $a['type'] === 'province'), 'code'))],
            $city => ['nullable', 'string', \Illuminate\Validation\Rule::in(array_column($cities, 'code'))],
        ];
    }
}
