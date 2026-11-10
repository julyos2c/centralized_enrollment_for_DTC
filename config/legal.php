<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Applicant-facing policy documents
    |--------------------------------------------------------------------------
    |
    | Text lives in resources/views/legal/. When the wording changes in a way
    | applicants must accept again, raise the version: each application keeps
    | the version it acknowledged (policy_acknowledgments), and earlier records
    | are never rewritten.
    |
    | The effective date is set when DTC management / the Data Protection
    | Officer approve a version. Until then the documents say so.
    |
    */

    'terms' => [
        'title' => 'Terms and Conditions — DTC Online Admission and Enrollment Portal',
        'version' => '1.0',
        'effective_date' => env('LEGAL_TERMS_EFFECTIVE_DATE'),
        'last_updated' => '2026-11-10',
    ],

    'privacy' => [
        'title' => 'Privacy Notice — DTC Online Admission and Enrollment Portal',
        'version' => '1.0',
        'effective_date' => env('LEGAL_PRIVACY_EFFECTIVE_DATE'),
        'last_updated' => '2026-11-10',
    ],

    'sensitive_consent' => [
        'title' => 'Consent to process optional sensitive personal information',
        'version' => '1.0',
        'effective_date' => env('LEGAL_PRIVACY_EFFECTIVE_DATE'),
        'last_updated' => '2026-11-10',
    ],

    // Optional application fields that hold sensitive personal information
    // (Data Privacy Act, sec. 3(l)); filling any of them needs separate consent.
    'sensitive_optional_fields' => ['religion', 'marital_status', 'spouse_name', 'disability', 'pwd_id'],
];
