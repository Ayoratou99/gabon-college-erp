<?php

declare(strict_types=1);

return [
    'name' => 'Reporting',

    /*
    |--------------------------------------------------------------------------
    | Cache
    |--------------------------------------------------------------------------
    |
    | Aggregation queries are cheap relative to most app pages, but the
    | reporting dashboard hits ~6 endpoints on render. Cache aggressively
    | per (session, user) — invalidated whenever Concours writes happen.
    */
    'cache' => [
        'enabled' => true,
        'store'   => env('REPORTING_CACHE_STORE', env('CACHE_STORE', 'file')),
        'ttl'     => (int) env('REPORTING_CACHE_TTL', 120), // 2 min — fresh enough for live dashboards
        'prefix'  => 'cuk:reporting:',
    ],
];
