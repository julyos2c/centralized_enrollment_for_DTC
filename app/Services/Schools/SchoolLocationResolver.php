<?php

namespace App\Services\Schools;

use App\Services\Psgc\PostalCodeMatcher;
use App\Services\PsgcLocationCatalog;

/**
 * Maps a school's region / province / municipality as written in a DepEd
 * list to PSGC codes from the address catalog. Returns nulls for parts it
 * cannot match with confidence; applicants can complete those themselves.
 */
class SchoolLocationResolver
{
    /** DepEd region labels (including pre-2019 names) => PSGC region codes. */
    private const REGIONS = [
        'region i' => '0100000000', 'ilocos' => '0100000000',
        'region ii' => '0200000000', 'cagayan valley' => '0200000000',
        'region iii' => '0300000000', 'central luzon' => '0300000000',
        'region iv a' => '0400000000', 'calabarzon' => '0400000000',
        'region iv b' => '1700000000', 'mimaropa' => '1700000000',
        'region v' => '0500000000', 'bicol' => '0500000000',
        'region vi' => '0600000000', 'western visayas' => '0600000000',
        'region vii' => '0700000000', 'central visayas' => '0700000000',
        'region viii' => '0800000000', 'eastern visayas' => '0800000000',
        'region ix' => '0900000000', 'zamboanga peninsula' => '0900000000',
        'region x' => '1000000000', 'northern mindanao' => '1000000000',
        'region xi' => '1100000000', 'davao' => '1100000000',
        'region xii' => '1200000000', 'soccsksargen' => '1200000000',
        'ncr' => '1300000000', 'national capital region' => '1300000000',
        'car' => '1400000000', 'cordillera administrative region' => '1400000000',
        'caraga' => '1600000000', 'region xiii' => '1600000000',
        'nir' => '1800000000', 'negros island region' => '1800000000',
        'armm' => '1900000000', 'barmm' => '1900000000',
    ];

    /** Official renames: Kalookan -> Caloocan; Baliuag -> Baliwag (2022 cityhood). */
    private const ALIASES = [
        'kalookan' => 'caloocan',
        'baliuag' => 'baliwag',
    ];

    /** Manila's districts appear as municipalities in DepEd lists; PSGC lists them under the City of Manila. */
    private const MANILA_DISTRICTS = [
        'tondo', 'tondo i ii', 'binondo', 'quiapo', 'san nicolas', 'santa cruz', 'sampaloc', 'san miguel',
        'ermita', 'intramuros', 'malate', 'paco', 'pandacan', 'port area', 'santa ana',
    ];

    private PostalCodeMatcher $names;

    private ?array $cities = null;

    private ?array $provinces = null;

    public function __construct(private PsgcLocationCatalog $catalog)
    {
        $this->names = new PostalCodeMatcher();
    }

    /** @return array{region_code: ?string, province_code: ?string, city_code: ?string} */
    public function resolve(?string $region, ?string $province, ?string $municipality): array
    {
        $this->load();
        $regionCode = self::REGIONS[$this->key($region)] ?? null;

        // Province names are unique nationally; regions are not compared because
        // several provinces changed region since older DepEd lists (ARMM -> BARMM, NIR).
        // Pseudo-provinces ("City of Cebu (Independent City)") are not provinces.
        $provinceKey = $this->names->normalize((string) $province);
        $provinceKey = $provinceKey === 'north cotabato' ? 'cotabato' : $provinceKey; // PSGC name is "Cotabato"
        $provinceCodes = [];
        foreach ($this->provinces as $p) {
            if (empty($p['is_pseudo']) && $this->names->normalize($p['name']) === $provinceKey) {
                $provinceCodes[] = $p['psgc_code'];
            }
        }

        $wanted = $this->names->normalize((string) $municipality);
        $wanted = self::ALIASES[$wanted] ?? $wanted;
        if ($regionCode === '1300000000' && in_array($wanted, self::MANILA_DISTRICTS, true)) {
            $wanted = 'manila';
        }
        $city = null;
        if ($wanted !== '') {
            // Spacing and hyphens differ between lists ("Jalajala" / "Jala-Jala").
            $compact = str_replace(' ', '', $wanted);
            $candidates = array_values(array_filter($this->cities, fn ($c) => str_replace(' ', '', $this->names->normalize($c['name'])) === $compact));
            if (!$candidates && $provinceCodes) {
                $candidates = $this->fuzzy($wanted, $provinceCodes);
            }
            $inProvince = array_values(array_filter($candidates, fn ($c) => in_array($c['province_code'], $provinceCodes, true)));
            $inRegion = $regionCode ? array_values(array_filter($candidates, fn ($c) => $c['region_code'] === $regionCode)) : [];
            foreach ([$inProvince, $inRegion, $candidates] as $set) {
                if (count($set) === 1) {
                    $city = $set[0];
                    break;
                }
                if (count($set) > 1) {
                    break;
                }
            }
        }

        if ($city) {
            $province = collect($this->provinces)->firstWhere('psgc_code', $city['province_code']);
            $isRealProvince = $province && empty($province['is_pseudo']);

            return [
                'region_code' => $city['region_code'],
                'province_code' => $isRealProvince ? $city['province_code'] : null,
                'city_code' => $city['psgc_code'],
            ];
        }

        return [
            'region_code' => $regionCode,
            'province_code' => count($provinceCodes) === 1 ? $provinceCodes[0] : null,
            'city_code' => null,
        ];
    }

    /** Spelling-variant match limited to the school's province; one clear winner only. */
    private function fuzzy(string $wanted, array $provinceCodes): array
    {
        $best = null;
        $bestScore = 0.0;
        $runnerUp = 0.0;
        foreach ($this->cities as $city) {
            if (!in_array($city['province_code'], $provinceCodes, true)) {
                continue;
            }
            $name = $this->names->normalize($city['name']);
            $max = max(strlen($name), strlen($wanted));
            $score = $max < 5 ? 0.0 : 1 - levenshtein($name, $wanted) / $max;
            if ($score > $bestScore) {
                [$runnerUp, $bestScore, $best] = [$bestScore, $score, $city];
            } elseif ($score > $runnerUp) {
                $runnerUp = $score;
            }
        }

        return $best && $bestScore >= 0.8 && $bestScore - $runnerUp >= 0.1 ? [$best] : [];
    }

    private function key(?string $label): string
    {
        $label = strtolower(trim((string) $label));
        $label = preg_replace('/\(.*?\)/', '', $label);

        return trim(preg_replace('/[^a-z0-9]+/', ' ', $label));
    }

    private function load(): void
    {
        if ($this->cities !== null) {
            return;
        }
        $path = $this->catalog->dataPath();
        $this->cities = json_decode(file_get_contents($path . '/cities.json'), true);
        $this->provinces = json_decode(file_get_contents($path . '/provinces.json'), true);
    }
}
