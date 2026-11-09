<?php

namespace App\Services\Psgc;

/**
 * Links PHLPost ZIP codes (keyed by town/area names) to PSGC city and
 * municipality codes. The ZIP list spells many towns differently from PSGC
 * ("Naguillan" vs "Naguilian"), so matching is exact first, then a
 * conservative fuzzy match restricted to the same province. Anything not
 * matched confidently is left out: applicants type those postal codes.
 */
class PostalCodeMatcher
{
    private const NCR = '1300000000';

    private const ALIASES = [
        'markina' => 'marikina',
    ];

    /** @var array<string, array<int, array>> */
    private array $byName = [];

    /** @var array<string, array<int, string>> normalized province name => province codes */
    private array $provinceCodes = [];

    /** @var array<string, string> province code => region code */
    private array $provinceRegion = [];

    /**
     * @param array<string, array{area?: string, city?: string, province?: string}> $zipCodes
     * @return array<string, array<int, array{code: string, area: string}>>
     */
    public function match(array $zipCodes, array $cities, array $provinces): array
    {
        $this->index($cities, $provinces);
        $postal = [];

        foreach ($zipCodes as $zip => $entry) {
            $zip = (string) $zip;
            if (!preg_match('/^\d{4}$/', $zip) || !is_array($entry)) {
                continue;
            }
            $cityName = trim((string) ($entry['city'] ?? ''));
            $area = trim((string) ($entry['area'] ?? ''));
            $province = trim((string) ($entry['province'] ?? ''));
            $name = $cityName !== '' ? $cityName : $area;
            if ($name === '') {
                continue;
            }

            // "Alcala / Baggao" covers both towns.
            foreach (preg_split('#\s*/\s*#', $name) as $part) {
                $city = $this->find($part, $province);
                if (!$city) {
                    continue;
                }
                $label = $cityName !== '' && $area !== '' && $this->normalize($area) !== $this->normalize($city['name']) ? $area : '';
                $postal[$city['psgc_code']][$zip] = ['code' => $zip, 'area' => $label];
            }
        }

        ksort($postal);
        foreach ($postal as &$entries) {
            ksort($entries);
            $entries = array_values($entries);
        }

        return $postal;
    }

    private function index(array $cities, array $provinces): void
    {
        $this->byName = [];
        $this->provinceCodes = [];
        $this->provinceRegion = [];
        foreach ($provinces as $p) {
            $this->provinceCodes[$this->normalize($p['name'])][] = $p['psgc_code'];
            $this->provinceRegion[$p['psgc_code']] = $p['region_code'];
        }
        foreach ($cities as $c) {
            $this->byName[$this->normalize($c['name'])][] = $c;
        }
    }

    private function find(string $name, string $province): ?array
    {
        $key = $this->normalize($name);
        if ($key === '') {
            return null;
        }

        $scope = $this->scope($province);
        $exact = $this->narrow($this->byName[$key] ?? [], $scope);
        if (count($exact) === 1) {
            return $exact[0];
        }
        if (count($exact) > 1 || $scope === null) {
            return null;
        }

        // Fuzzy: best single candidate in the same province, then region.
        foreach ([$scope['provinces'], null] as $provinceFilter) {
            $best = null;
            $bestScore = 0.0;
            $runnerUp = 0.0;
            foreach ($this->byName as $candidateKey => $candidates) {
                foreach ($candidates as $candidate) {
                    $inScope = $provinceFilter !== null
                        ? in_array($candidate['province_code'], $provinceFilter, true)
                        : $candidate['region_code'] === $scope['region'] || in_array($candidate['region_code'], $scope['regions'], true);
                    if (!$inScope) {
                        continue;
                    }
                    $score = $this->similarity($key, $candidateKey);
                    if ($score > $bestScore) {
                        $runnerUp = $bestScore;
                        $bestScore = $score;
                        $best = $candidate;
                    } elseif ($score > $runnerUp) {
                        $runnerUp = $score;
                    }
                }
            }
            if ($best && $bestScore >= 0.84 && $bestScore - $runnerUp >= 0.08) {
                return $best;
            }
        }

        return null;
    }

    /** Restrict candidates to the ZIP entry's province (or NCR). */
    private function narrow(array $candidates, ?array $scope): array
    {
        if (count($candidates) <= 1 || $scope === null) {
            return $candidates;
        }
        $sameProvince = array_values(array_filter($candidates, fn ($c) => in_array($c['province_code'], $scope['provinces'], true)));
        if ($sameProvince) {
            return $sameProvince;
        }

        return array_values(array_filter($candidates, fn ($c) => in_array($c['region_code'], $scope['regions'], true)));
    }

    private function scope(string $province): ?array
    {
        if (strcasecmp($province, 'NCR') === 0 || stripos($province, 'Metro Manila') !== false) {
            return ['provinces' => [self::NCR], 'regions' => [self::NCR], 'region' => self::NCR];
        }
        $codes = $this->provinceCodes[$this->normalize($province)] ?? [];
        if (!$codes) {
            return null;
        }
        $regions = array_values(array_unique(array_map(fn ($code) => $this->provinceRegion[$code], $codes)));

        return ['provinces' => $codes, 'regions' => $regions, 'region' => $regions[0]];
    }

    private function similarity(string $a, string $b): float
    {
        if ($a === $b) {
            return 1.0;
        }
        $max = max(strlen($a), strlen($b));
        if ($max < 5) {
            return 0.0;
        }

        return 1 - levenshtein($a, $b) / $max;
    }

    public function normalize(string $value): string
    {
        $value = \Illuminate\Support\Str::ascii($value);
        $value = strtolower($value);
        $value = preg_replace('/\(.*?\)/', '', $value);
        $value = preg_replace('/\bsto\.?\s/', 'santo ', $value);
        $value = preg_replace('/\bsta\.?\s/', 'santa ', $value);
        $value = preg_replace('/\bgen\.?\s/', 'general ', $value);
        $value = preg_replace('/\bpres\.?\s/', 'president ', $value);
        $value = preg_replace('/\b(science city of|city of|municipality of|city)\b/', '', $value);
        $value = preg_replace('/[^a-z0-9]+/', ' ', $value);
        $value = trim(preg_replace('/\s+/', ' ', $value));

        return self::ALIASES[$value] ?? $value;
    }
}
