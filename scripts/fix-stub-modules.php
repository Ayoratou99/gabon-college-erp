<?php
declare(strict_types=1);

/**
 * One-shot fixer for the 9 stub modules generated via a broken bash heredoc.
 * Run with: php scripts/fix-stub-modules.php
 *
 * Re-writes module.json, composer.json, ServiceProvider, and routes/web.php
 * for each of the 9 empty modules, using PHP's clean string handling.
 */

$root = realpath(__DIR__ . '/..');
$modules = [
    'Scolarite'         => 'scolarite',
    'Enseignements'     => 'enseignements',
    'EmploiDuTemps'     => 'emploi-du-temps',
    'Evaluations'       => 'evaluations',
    'Examens'           => 'examens',
    'ResultatsDiplomes' => 'resultats-diplomes',
    'Presences'         => 'presences',
    'Finances'          => 'finances',
    'Communication'     => 'communication',
];

foreach ($modules as $name => $slug) {
    $base = "$root/Modules/$name";
    @mkdir("$base/app/Providers", 0777, true);
    @mkdir("$base/routes", 0777, true);
    @mkdir("$base/database/migrations", 0777, true);

    // ---- module.json ----
    file_put_contents("$base/module.json", json_encode([
        'name'        => $name,
        'alias'       => $slug,
        'description' => "Module $name — squelette à compléter.",
        'keywords'    => [],
        'priority'    => 10,
        'providers'   => ["Modules\\$name\\Providers\\{$name}ServiceProvider"],
        'files'       => [],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");

    // ---- composer.json ----
    file_put_contents("$base/composer.json", json_encode([
        'name'        => "cuk/$slug",
        'description' => "Module $name.",
        'type'        => 'laravel-module',
        'license'     => 'proprietary',
        'require'     => new \stdClass(),
        'autoload'    => [
            'psr-4' => [
                "Modules\\$name\\"                       => 'app/',
                "Modules\\$name\\Database\\Factories\\"  => 'database/factories/',
                "Modules\\$name\\Database\\Seeders\\"    => 'database/seeders/',
            ],
        ],
        'extra'       => [
            'laravel' => [
                'providers' => ["Modules\\$name\\Providers\\{$name}ServiceProvider"],
            ],
        ],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");

    // ---- ServiceProvider ----
    $provider = <<<PHP
        <?php

        declare(strict_types=1);

        namespace Modules\\$name\\Providers;

        use Illuminate\\Support\\ServiceProvider;

        final class {$name}ServiceProvider extends ServiceProvider
        {
            public function register(): void {}

            public function boot(): void
            {
                \$this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');
            }
        }

        PHP;

    // Strip the common leading whitespace from the heredoc.
    $provider = preg_replace('/^        /m', '', $provider);
    file_put_contents("$base/app/Providers/{$name}ServiceProvider.php", $provider);

    // ---- routes/web.php ----
    file_put_contents("$base/routes/web.php", <<<PHP
        <?php

        declare(strict_types=1);

        // Routes for $name land here once the module is implemented.

        PHP);

    echo "fixed: $name\n";
}
echo "done.\n";
