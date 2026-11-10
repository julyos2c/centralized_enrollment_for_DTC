<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * Philippine school years ("2024-2025") for the applicant's own academic
 * history. The current school year turns over in June, when the basic
 * education school year traditionally opens.
 */
class AcademicYears
{
    public const EARLIEST_START = 1970;

    public static function current(?Carbon $today = null): string
    {
        $today ??= Carbon::now(config('app.timezone'));
        $start = $today->month >= 6 ? $today->year : $today->year - 1;

        return $start . '-' . ($start + 1);
    }

    /** Newest first, from the current school year back to EARLIEST_START. */
    public static function options(?Carbon $today = null): array
    {
        $currentStart = (int) substr(self::current($today), 0, 4);
        $years = [];
        for ($start = $currentStart; $start >= self::EARLIEST_START; $start--) {
            $years[] = $start . '-' . ($start + 1);
        }

        return $years;
    }

    /** Start year of a "YYYY-YYYY" (or "YYYY–YYYY") value, or null when it is not one. */
    public static function startYear(?string $value): ?int
    {
        if ($value === null || !preg_match('/^\s*(\d{4})\s*[-–—]\s*(\d{4})\s*$/u', $value, $m)) {
            return null;
        }

        return (int) $m[2] === (int) $m[1] + 1 ? (int) $m[1] : null;
    }
}
