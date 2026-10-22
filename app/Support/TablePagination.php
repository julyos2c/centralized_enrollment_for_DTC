<?php

namespace App\Support;

class TablePagination
{
    public const OPTIONS = [5, 10, 15, 20, 25, 50];

    public static function perPage(int $default = 10): int
    {
        $value = request()->query('per_page');
        if (!is_scalar($value) || !ctype_digit((string) $value)) {
            return $default;
        }

        $limit = (int) $value;
        return in_array($limit, self::OPTIONS, true) ? $limit : $default;
    }
}
