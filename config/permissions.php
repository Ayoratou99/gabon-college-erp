<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Cache
    |--------------------------------------------------------------------------
    |
    | Per-user permission lookups are cached in Redis so the auth payload
    | doesn't hit the DB on every request. The cache is invalidated whenever
    | a role/permission assignment changes (see UserManagement service).
    */

    'cache' => [
        'enabled' => true,
        'store'   => env('PERMISSIONS_CACHE_STORE', 'redis'),
        'ttl'     => (int) env('PERMISSIONS_CACHE_TTL', 3600),
        'prefix'  => 'cuk:perm:',
    ],

    /*
    |--------------------------------------------------------------------------
    | Declared scopes
    |--------------------------------------------------------------------------
    |
    | The set of valid scope keys, enforced at boot when a module declares
    | its permission catalog. To add a new scope key, register a resolver
    | (App\Foundation\Permissions\Contracts\ScopeResolver) and add the key
    | here.
    */

    'scopes' => [
        '*',
        'own',
        'own_center',
        'own_region',
        'own_session',
    ],

    /*
    |--------------------------------------------------------------------------
    | Declared actions
    |--------------------------------------------------------------------------
    |
    | Reserved verbs; modules should pick from this list when declaring
    | permissions. Add new ones sparingly — every verb is a UI label.
    */

    'actions' => [
        '*',
        'view',
        'create',
        'edit',
        'delete',
        'restore',     // soft-delete recovery
        'validate',    // dossier acceptance
        'reject',      // dossier rejection with motif
        'export',
        'import',
        'manage',      // catch-all for module-wide admin
        'publish',     // results
    ],

    /*
    |--------------------------------------------------------------------------
    | Strict mode
    |--------------------------------------------------------------------------
    |
    | When true, every permission string used at runtime must have been
    | declared via PermissionRegistry::declare(). Catches typos early.
    | Turn off only for incremental migration.
    */

    'strict' => env('PERMISSIONS_STRICT', true),
];
