<?php

return [

    /*
    |--------------------------------------------------------------------------
    | PSGC address data
    |--------------------------------------------------------------------------
    |
    | The applicant address picker reads a PSGC snapshot from local files only;
    | no request ever waits on an external service. The snapshot committed in
    | resources/data/psgc is always available. `php artisan psgc:sync` can
    | download a newer copy into storage/app/psgc; the app prefers that copy
    | while it is valid and falls back to the bundled snapshot otherwise.
    |
    | No credentials are needed: the source is a public, MIT-licensed
    | republication of the PSA PSGC masterlist and PHLPost ZIP codes.
    |
    */

    'source_url' => env('PSGC_SOURCE_URL', 'https://raw.githubusercontent.com/fish-and-bear/psgc/main/psgc/data/core'),

    // Seconds to wait for each source file during psgc:sync.
    'source_timeout' => (int) env('PSGC_SOURCE_TIMEOUT', 120),

    // Minimum record counts a downloaded snapshot must reach before it is used.
    'minimum_counts' => [
        'regions' => 17,
        'provinces' => 80,
        'cities' => 1600,
        'barangays' => 40000,
    ],

    // Browser cache lifetime for location API responses (URLs are versioned).
    'http_max_age' => 86400 * 7,
];
