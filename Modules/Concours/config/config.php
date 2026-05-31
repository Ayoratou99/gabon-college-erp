<?php

declare(strict_types=1);

return [
    'name' => 'Concours',

    /*
    |--------------------------------------------------------------------------
    | Storage paths
    |--------------------------------------------------------------------------
    |
    | New uploads land under storage/app/private/candidats/{annee}/{candidat_id}/...
    | Legacy folders (old PHP app's `documentcupk` / `imageprofilecupk`) are
    | resolved relative to the project root by default — drop them next to
    | the `cuk-app/` directory or override via .env.
    */
    'storage' => [
        'disk'      => env('CONCOURS_DISK', 'local'),
        'documents' => 'candidats/%s/%s/documents/%s.%s',     // year, candidat_id, doc_code, ext
        'photo'     => 'candidats/%s/%s/photo.%s',            // year, candidat_id, ext
        'legacy'    => [
            'documents' => env('LEGACY_DOCUMENTS_PATH', base_path('../legacy/documentcupk')),
            'photos'    => env('LEGACY_PROFILE_IMAGES_PATH', base_path('../legacy/imageprofilecupk')),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Public lookup (modification d'un dossier rejeté)
    |--------------------------------------------------------------------------
    */
    'public_lookup' => [
        'throttle' => [
            'max_attempts'  => 5,
            'decay_seconds' => 900, // 15 min
        ],
        'modification_token_ttl' => 3600, // 1h to actually submit the changes
        'session_keys' => [
            'modification_token'    => 'concours.modification.token',
            'modification_candidat' => 'concours.modification.candidat_id',
            'modification_expires'  => 'concours.modification.expires_at',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | eBilling (paiement)
    |--------------------------------------------------------------------------
    |
    | All credentials live in .env — they are operational secrets, not
    | tenant-configurable settings, and we want zero secrets in the database.
    | Rotating keys = update .env + `docker compose up -d --build`.
    */
    'ebilling' => [
        'base_url'            => env('EBILLING_BASE_URL', 'https://lab.billing-easy.net'),
        // The *portal* URL is the public hosted payment page the candidat is
        // redirected to with their invoice id; base_url above is the REST API
        // used server-to-server. They live on the same domain in practice but
        // we keep the indirection so staging vs prod can swap independently.
        'portal_url'          => env('EBILLING_PORTAL_URL', env('EBILLING_BASE_URL', 'https://lab.billing-easy.net')),
        'username'            => env('EBILLING_USERNAME', ''),
        'shared_key'          => env('EBILLING_SHARED_KEY', ''),
        'http_timeout'        => (int) env('EBILLING_HTTP_TIMEOUT', 8),
        'callback_route_name' => 'concours.payment.callback',
        // 32-byte symmetric key used to AES-256-GCM the external_reference
        // we send to eBilling. We rely entirely on this — eBilling does NOT
        // sign the callback body, so the only way to know a callback is
        // genuine is that the reference round-trips through our key.
        // Generate with:
        //   php -r "echo 'base64:' . base64_encode(random_bytes(32));"
        'reference_key'       => env('EBILLING_REFERENCE_KEY', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | Payment defaults — fallback used when the active session has no
    | per-session override and the SettingsService can't be reached.
    |--------------------------------------------------------------------------
    */
    'payment' => [
        'default_amount' => (int) env('CONCOURS_DEFAULT_FEE', 10300),
    ],

    /*
    |--------------------------------------------------------------------------
    | Prod test candidate (QA backdoor)
    |--------------------------------------------------------------------------
    |
    | When CONCOURS_TEST_EMAIL is set, registering with that exact email on an
    | open session creates/updates a SINGLE "test" candidate (matricule
    | CUK-{year}-00000, flagged is_test=true) so the full register → accept →
    | pay → validate flow can be exercised on production without polluting the
    | real figures:
    |   - it is hidden from the dashboard, reporting, candidate list and
    |     payments list for every role EXCEPT super-admin;
    |   - only super-admin may view / validate / reject it;
    |   - its eBilling invoice is charged `fee` (default 100) instead of the
    |     real inscription fee.
    |
    | Leave CONCOURS_TEST_EMAIL empty to disable the backdoor entirely.
    */
    'test' => [
        'email' => mb_strtolower(trim((string) env('CONCOURS_TEST_EMAIL', ''))),
        'fee'   => (int) env('CONCOURS_TEST_FEE', 100),
    ],

    /*
    |--------------------------------------------------------------------------
    | Candidate statuses (machine values)
    |--------------------------------------------------------------------------
    */
    'statuses' => [
        'NON'    => 'non',      // submitted, awaiting review
        'OUI'    => 'oui',      // accepted, awaiting payment
        'VALID'  => 'valid',    // payment confirmed
        'REJETE' => 'rejete',   // rejected with motifs
        'ADMIS'  => 'admis',    // selected after results (Stage 5B)
    ],
];
