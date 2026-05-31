<?php

declare(strict_types=1);

return [
    'name' => 'Parametrage',

    /*
    |--------------------------------------------------------------------------
    | Settings cache
    |--------------------------------------------------------------------------
    |
    | Settings are read on nearly every request (homepage, fees, eBilling).
    | We aggressively cache the resolved key→value map and invalidate it on
    | every write through SettingsService.
    */
    'cache' => [
        'enabled' => true,
        'store'   => env('SETTINGS_CACHE_STORE', env('CACHE_STORE', 'file')),
        'key'     => 'cuk:settings:map',
        'ttl'     => (int) env('SETTINGS_CACHE_TTL', 3600),
    ],

    /*
    |--------------------------------------------------------------------------
    | Supported value types
    |--------------------------------------------------------------------------
    |
    | Each setting declares its type; the caster coerces stored TEXT to/from
    | the typed PHP value at the application boundary. Adding a new type
    | means adding a case in SettingValueCaster.
    */
    'types' => [
        'string',
        'text',          // long-form text
        'integer',
        'decimal',
        'boolean',
        'json',          // arbitrary structure (homepage sections, role lists)
        'image_url',     // string that points to an uploaded asset
        'email',
        'phone',
        'url',
    ],

    /*
    |--------------------------------------------------------------------------
    | Categories
    |--------------------------------------------------------------------------
    | Used to group settings in the admin UI.
    */
    // eBilling credentials live in .env / config('concours.ebilling.*') —
    // they're operational secrets, not tenant settings, so there's no tab
    // for them in the admin UI.
    'categories' => [
        'concours'   => 'Concours & frais',
        'site'       => 'Site public',
        'security'   => 'Sécurité',
        'support'    => 'Support / contact',
    ],
];
