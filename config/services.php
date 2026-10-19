<?php

return [

    'application_status_lookup' => [
        'per_ip' => 20,
        'per_identity' => 5,
    ],

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */
    // Five POST /apply attempts per source IP per hour. Applicants behind
    // a shared campus NAT share this allowance.
    'application_submission' => [
        'per_ip_per_hour' => env('APPLICATION_SUBMISSION_LIMIT_PER_IP', 5),
    ],

    // Bot protection is handled at the Cloudflare edge; these limits are
    // the server-side backstop.
    'login_rate_limit' => [
        'per_identity_attempts' => env('LOGIN_RATE_LIMIT_PER_IDENTITY', 5),
        'per_ip_attempts' => env('LOGIN_RATE_LIMIT_PER_IP', 300),
        'decay_seconds' => env('LOGIN_RATE_LIMIT_DECAY_SECONDS', 300),
        // Per email from any IP (the client IP can be spoofed at the origin).
        'per_email_attempts' => env('LOGIN_RATE_LIMIT_PER_EMAIL', 20),
        'per_email_decay_seconds' => env('LOGIN_RATE_LIMIT_PER_EMAIL_DECAY_SECONDS', 900),
    ],

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

];
