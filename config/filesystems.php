<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

        /*
        | "legacy" disk — points at the old PHP application's documentcupk
        | folder. The Concours module's legacy importer stamps `disk='legacy'`
        | on every CandidatDocument row it creates so the admin can still
        | preview / download those files via the same pipeline as new uploads.
        | LEGACY_DOCUMENTS_PATH defaults to `./legacy/documentcupk` (relative
        | to the cuk-app root) — drop the folder next to cuk-app/ or override
        | via .env in prod.
        */
        'legacy' => [
            'driver' => 'local',
            'root' => env('LEGACY_DOCUMENTS_PATH', base_path('../legacy/documentcupk')),
            // No `serve: true`: legacy files must NOT be reachable via the
            // generic /storage/{path} URL (would bypass our preview
            // permission gate). They only go through CandidatDocumentController.
            'throw' => false,
            'report' => false,
        ],

        /*
        | "legacy_photos" disk — old PHP application's imageprofilecupk folder.
        | The legacy `etudiants` table never stored a photo path (the photo file
        | name was computed from the candidat's id at template time), so we
        | don't stamp `photo_path` at import time. Instead, the admin photo
        | endpoint probes a small set of filename conventions against this disk
        | when a candidat has a `legacy_id` but no `photo_path` — production
        | only needs to drop the imageprofilecupk folder at LEGACY_PROFILE_IMAGES_PATH
        | and the photos resolve automatically.
        |
        | Probed patterns (in order, with .jpg .jpeg .png .webp extensions):
        |   {annee}user{idetu}.{ext}
        |   {annee}user{idetu}profile.{ext}
        |   {annee}user{idetu}profil.{ext}
        |   user{idetu}.{ext}
        |   {idetu}.{ext}
        */
        'legacy_photos' => [
            'driver' => 'local',
            'root' => env('LEGACY_PROFILE_IMAGES_PATH', base_path('../legacy/imageprofilecupk')),
            'throw' => false,
            'report' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
