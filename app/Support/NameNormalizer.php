<?php

namespace App\Support;

class NameNormalizer
{
    public static function normalizeAffix(?string $value, string $field): ?string
    {
        if ($value === null) return null;
        $value = preg_replace('/\s+/u', ' ', trim($value)) ?? trim($value);
        if ($field === 'suffix') {
            if (preg_match('/^(jr|sr)\.?$/i', $value, $match)) return ucfirst(strtolower($match[1])).'.';
            if (preg_match('/^(ii|iii|iv|v|vi|vii|viii|ix|x)$/i', $value)) return strtoupper($value);
        }
        if ($field === 'honorific') {
            $titles = ['mr' => 'Mr', 'mrs' => 'Mrs', 'ms' => 'Ms', 'mx' => 'Mx', 'dr' => 'Dr',
                'prof' => 'Prof', 'engr' => 'Engr', 'atty' => 'Atty', 'rev' => 'Rev', 'fr' => 'Fr',
                'sr' => 'Sr', 'br' => 'Br', 'hon' => 'Hon', 'capt' => 'Capt', 'gen' => 'Gen',
                'lt' => 'Lt', 'col' => 'Col', 'maj' => 'Maj', 'sgt' => 'Sgt'];
            $value = preg_replace_callback('/^([a-z]+)\.?(?=\s|$)/i', function ($match) use ($titles) {
                $title = $titles[strtolower($match[1])] ?? null;
                return $title ? $title.'.' : $match[0];
            }, $value) ?? $value;
        }
        return $value;
    }

    /** Normalize spacing and title-case letter groups without removing punctuation. */
    public static function normalize(?string $name): ?string
    {
        if ($name === null) {
            return null;
        }

        $name = preg_replace('/\s+/u', ' ', trim($name));
        if ($name === null || $name === '') {
            return $name;
        }

        $name = mb_strtolower($name, 'UTF-8');

        $normalized = preg_replace_callback('/[\p{L}\p{M}]+/u', function (array $match) {
            $word = $match[0];
            $firstCharacter = mb_substr($word, 0, 1, 'UTF-8');

            return mb_strtoupper($firstCharacter, 'UTF-8') . mb_substr($word, 1, null, 'UTF-8');
        }, $name) ?? $name;

        // Ordinal suffixes remain lowercase when attached to a number (2nd year).
        return preg_replace_callback('/(?<=\d)(st|nd|rd|th)\b/ui', function (array $match) {
            return mb_strtolower($match[0], 'UTF-8');
        }, $normalized) ?? $normalized;
    }
}
