<?php

namespace App\Support;

class ApplicationEducation
{
    public const LEVELS = [
        'new_student' => ['Senior High School', 'Other'],
        'transferee' => ['College'],
        'shiftee' => ['College'],
        'returnee' => ['College'],
        'cross_enrollee' => ['College'],
    ];

    public static function levelsFor(?string $type): array
    {
        return self::LEVELS[$type ?? ''] ?? [];
    }
}
