<?php

declare(strict_types=1);

return [
    'name' => 'UserManagement',

    /*
    |--------------------------------------------------------------------------
    | Login throttle tiers
    |--------------------------------------------------------------------------
    |
    | The login flow checks BOTH tiers on each attempt. The "fast" tier exists
    | to slow down a focused brute-force; the "slow" tier acts as the long-term
    | safety net and triggers an email alert when a known account is hit.
    */
    'throttle' => [
        'fast' => [
            'max_attempts'    => (int) env('LOGIN_THROTTLE_FAST_MAX', 3),
            'decay_seconds'   => (int) env('LOGIN_THROTTLE_FAST_DECAY', 900),
        ],
        'slow' => [
            'max_attempts'    => (int) env('LOGIN_THROTTLE_SLOW_MAX', 5),
            'decay_seconds'   => (int) env('LOGIN_THROTTLE_SLOW_DECAY', 86400),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | reCAPTCHA v3
    |--------------------------------------------------------------------------
    */
    'recaptcha' => [
        'enabled'   => filled(env('NOCAPTCHA_SECRET')),
        'site_key'  => env('NOCAPTCHA_SITEKEY'),
        'secret'    => env('NOCAPTCHA_SECRET'),
        'min_score' => (float) env('RECAPTCHA_MIN_SCORE', 0.5),
        'verify_url' => 'https://www.google.com/recaptcha/api/siteverify',
        'timeout'   => 5,
        // Skip the challenge entirely when the request host matches one of
        // these — even if NOCAPTCHA_SECRET is set in a shared .env. Comma-
        // separated env override available.
        'skip_hosts' => array_filter(explode(',', (string) env(
            'RECAPTCHA_SKIP_HOSTS', 'localhost,127.0.0.1,::1,cuk.test',
        ))),
    ],

    /*
    |--------------------------------------------------------------------------
    | Two-factor authentication (Google Authenticator)
    |--------------------------------------------------------------------------
    */
    'two_factor' => [
        'enabled'         => (bool) env('GOOGLE2FA_ENABLED', true),
        'issuer'          => env('GOOGLE2FA_ISSUER', 'Concours CUK'),
        'window'          => (int) env('GOOGLE2FA_WINDOW', 1),
        'force_for_roles' => array_filter(explode(',', (string) env('GOOGLE2FA_FORCE_FOR_ROLES', 'super-admin,dg,de,chef-centre'))),
        'session_keys'    => [
            'pre_auth_user_id' => '2fa.pre_auth_user_id',
            'verified'         => '2fa.verified',
            'enrolling_secret' => '2fa.enrolling_secret',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Legacy password rehash
    |--------------------------------------------------------------------------
    |
    | If true, a successful login against a SHA1 legacy hash transparently
    | upgrades the user record to bcrypt and clears the legacy flag.
    */
    'legacy_password_rehash' => (bool) env('PASSWORD_LEGACY_REHASH', true),

    /*
    |--------------------------------------------------------------------------
    | Legacy admin dump import
    |--------------------------------------------------------------------------
    |
    | Path to the phpMyAdmin SQL dump used by LegacyAdminImportSeeder. We
    | parse the relevant INSERTs (utilisateurs, fonctions, chefs_de_centre)
    | and recreate the 13 admin accounts in Postgres so existing users keep
    | their identifiers and credentials.
    */
    'legacy_dump_path' => env('LEGACY_DUMP_PATH', storage_path('legacy/dump.sql')),
];
