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
    | Legacy paths (old PHP app) keep working via the read-only mount declared
    | in docker-compose under /legacy/documentcupk and /legacy/imageprofilecupk.
    */
    'storage' => [
        'disk'      => env('CONCOURS_DISK', 'local'),
        'documents' => 'candidats/%s/%s/documents/%s.%s',     // year, candidat_id, doc_code, ext
        'photo'     => 'candidats/%s/%s/photo.%s',            // year, candidat_id, ext
        'legacy'    => [
            'documents' => env('LEGACY_DOCUMENTS_PATH', '/legacy/documentcupk'),
            'photos'    => env('LEGACY_PROFILE_IMAGES_PATH', '/legacy/imageprofilecupk'),
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
    | Read-only here; the actual values live in Parametrage so the admin can
    | rotate keys without redeploying.
    */
    'ebilling' => [
        'http_timeout' => 8, // seconds
        'callback_route_name' => 'concours.payment.callback',
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
