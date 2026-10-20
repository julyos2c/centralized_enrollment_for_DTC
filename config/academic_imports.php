<?php

return [
    'max_kilobytes' => (int) env('ACADEMIC_IMPORT_MAX_KB', 5120),
    'max_rows' => (int) env('ACADEMIC_IMPORT_MAX_ROWS', 1000),
    'preview_ttl_hours' => (int) env('ACADEMIC_IMPORT_PREVIEW_TTL_HOURS', 24),
];
