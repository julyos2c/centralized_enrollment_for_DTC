<?php

return [

    /*
    |--------------------------------------------------------------------------
    | School lookup scope
    |--------------------------------------------------------------------------
    |
    | The application form lists previous schools from this PSGC province only
    | (default: Bohol). Applicants from elsewhere choose "I studied outside
    | Bohol" and type their school. Set to an empty value to list every active
    | school in the reference table instead.
    |
    */

    'province_code' => env('SCHOOL_LOOKUP_PROVINCE_CODE', '0701200000'),

    'region_code' => env('SCHOOL_LOOKUP_REGION_CODE', '0700000000'),

    // Bundled reference list loaded by `php artisan schools:import`.
    'bundled_file' => 'data/schools/bohol-schools.csv',
];
