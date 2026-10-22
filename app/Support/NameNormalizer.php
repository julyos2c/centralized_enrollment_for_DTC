<?php

namespace App\Support;

class NameNormalizer
{
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
