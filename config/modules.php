<?php

declare(strict_types=1);

use Nwidart\Modules\Activators\FileActivator;
use Nwidart\Modules\Providers\ConsoleServiceProvider;

return [
    /*
    |--------------------------------------------------------------------------
    | Module namespace
    |--------------------------------------------------------------------------
    */
    'namespace' => 'Modules',

    /*
    |--------------------------------------------------------------------------
    | Module stubs (CUK-customised so generated files follow our conventions)
    |--------------------------------------------------------------------------
    */
    'stubs' => [
        'enabled' => false, // set true when we publish our own stubs in Stage 2
        'path'    => base_path('vendor/nwidart/laravel-modules/src/Commands/stubs'),
        'files'   => [
            'routes/web'        => 'Routes/web.php',
            'routes/api'        => 'Routes/api.php',
            'scaffold/config'   => 'Config/config.php',
            'composer'          => 'composer.json',
        ],
        'replacements' => [
            'routes/web'    => ['LOWER_NAME', 'STUDLY_NAME', 'MODULE_NAMESPACE', 'CONTROLLER_NAMESPACE'],
            'routes/api'    => ['LOWER_NAME', 'STUDLY_NAME', 'MODULE_NAMESPACE', 'CONTROLLER_NAMESPACE'],
            'composer'      => ['LOWER_NAME', 'STUDLY_NAME', 'VENDOR', 'AUTHOR_NAME', 'AUTHOR_EMAIL', 'MODULE_NAMESPACE', 'APP_FOLDER_NAME'],
        ],
        'gitkeep' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Module paths
    |--------------------------------------------------------------------------
    | We keep the `app/` subfolder of each module (so modules look like
    | mini Laravel apps internally) — required for the service-pattern
    | layout we want (Services/, DTOs/, Repositories/, Policies/).
    */
    'paths' => [
        'modules' => base_path('Modules'),
        'assets'  => public_path('modules'),
        'migration' => base_path('Modules/.shared/database/migrations'),
        'app_folder' => 'app/',
        'generator' => [
            // Each entry: [folder relative to module root, generate or skip]
            'channels'              => ['path' => 'app/Broadcasting', 'generate' => false],
            'command'               => ['path' => 'app/Console', 'generate' => true],
            'emails'                => ['path' => 'app/Emails', 'generate' => false],
            'event'                 => ['path' => 'app/Events', 'generate' => true],
            'jobs'                  => ['path' => 'app/Jobs', 'generate' => true],
            'listener'              => ['path' => 'app/Listeners', 'generate' => true],
            'model'                 => ['path' => 'app/Models', 'generate' => true],
            'notifications'         => ['path' => 'app/Notifications', 'generate' => false],
            'observer'              => ['path' => 'app/Observers', 'generate' => false],
            'policies'              => ['path' => 'app/Policies', 'generate' => true],
            'provider'              => ['path' => 'app/Providers', 'generate' => true],
            'route-provider'        => ['path' => 'app/Providers', 'generate' => true],
            'repository'            => ['path' => 'app/Repositories', 'generate' => false],
            'resource'              => ['path' => 'app/Transformers', 'generate' => false],
            'rules'                 => ['path' => 'app/Rules', 'generate' => false],
            'services'              => ['path' => 'app/Services', 'generate' => true],
            'dto'                   => ['path' => 'app/DTOs', 'generate' => true],
            'controller'            => ['path' => 'app/Http/Controllers', 'generate' => true],
            'filter'                => ['path' => 'app/Http/Middleware', 'generate' => false],
            'request'               => ['path' => 'app/Http/Requests', 'generate' => true],
            'config'                => ['path' => 'config', 'generate' => true],
            'command-migration'     => ['path' => 'database/migrations', 'generate' => true],
            'migration'             => ['path' => 'database/migrations', 'generate' => true],
            'seeder'                => ['path' => 'database/seeders', 'generate' => true],
            'factory'               => ['path' => 'database/factories', 'generate' => true],
            'views'                 => ['path' => 'resources/views', 'generate' => true],
            'assets'                => ['path' => 'resources/assets', 'generate' => false],
            'lang'                  => ['path' => 'resources/lang', 'generate' => true],
            'test-feature'          => ['path' => 'tests/Feature', 'generate' => true],
            'test-unit'             => ['path' => 'tests/Unit', 'generate' => true],
            'routes'                => ['path' => 'routes', 'generate' => true],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Auto-discover
    |--------------------------------------------------------------------------
    */
    'auto-discover' => [
        'migrations' => true,
        'translations' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Composer scripts
    |--------------------------------------------------------------------------
    */
    'scan' => [
        'enabled' => true,
        'paths' => [base_path('vendor/*/*')],
    ],

    'composer' => [
        'vendor' => 'cuk',
        'author' => [
            'name'  => 'CUK Engineering',
            'email' => 'eng@cuk.ga',
        ],
        'composer-output' => false,
    ],

    'cache' => [
        'enabled'  => env('MODULES_CACHE_ENABLED', true),
        'driver'   => 'file',
        'lifetime' => 60,
        'key'      => 'modules',
    ],

    'register' => [
        'translations' => true,
        'files'        => 'register',
    ],

    'activators' => [
        'file' => [
            'class'          => FileActivator::class,
            'statuses-file'  => base_path('modules_statuses.json'),
            'cache-key'      => 'activator.installed',
            'cache-lifetime' => 604800,
        ],
    ],

    'activator' => 'file',

    'providers' => [
        ConsoleServiceProvider::class,
    ],
];
