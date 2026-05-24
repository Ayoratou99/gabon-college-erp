<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Modules\Concours\Services\Legacy\LegacyImportOrchestrator;
use Modules\UserManagement\Services\LegacyDumpParser;

/**
 * Cutover one-shot:
 *
 *   php artisan cuk:legacy-import \
 *     --dump=storage/legacy/dump.sql      \
 *     [--tables=candidats,payments]        \
 *     [--dry-run]
 *
 * Imports concours_sessions → candidats → documents → motifs → payments,
 * with idempotency via `legacy_id` columns + natural-key fallbacks.
 *
 * Re-running is safe: anything already imported is skipped, not duplicated.
 * `--dry-run` runs every check and reports counts without writing.
 */
final class LegacyImportCommand extends Command
{
    protected $signature = 'cuk:legacy-import
        {--dump= : Path to the phpMyAdmin SQL dump (default: storage/legacy/dump.sql)}
        {--tables= : Comma-separated subset of: concours_sessions,candidats,candidat_documents,candidat_motifs_rejet,payments}
        {--dry-run : Run import logic without writing to the database}';

    protected $description = 'Import legacy MariaDB candidats / documents / motifs / payments into the new Postgres schema.';

    public function handle(LegacyImportOrchestrator $orchestrator): int
    {
        $dumpPath = (string) $this->option('dump') ?: storage_path('legacy/dump.sql');
        if (! is_file($dumpPath)) {
            $this->error("Dump introuvable : {$dumpPath}");
            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $tables = $this->option('tables')
            ? array_map('trim', explode(',', (string) $this->option('tables')))
            : [];

        $this->components->info(sprintf(
            'Lecture du dump : %s (%.1f Ko)',
            $dumpPath,
            filesize($dumpPath) / 1024,
        ));
        if ($dryRun) {
            $this->components->warn('Mode --dry-run : aucune écriture en base.');
        }
        if ($tables !== []) {
            $this->components->info('Tables ciblées : ' . implode(', ', $tables));
        }

        $parser = LegacyDumpParser::fromFile($dumpPath);

        $started = microtime(true);
        $report  = $orchestrator->run($parser, $tables, $dryRun);
        $elapsed = round(microtime(true) - $started, 2);

        $this->newLine();
        $this->components->info("Terminé en {$elapsed}s.");

        $this->table(
            ['Table', 'Importés', 'Ignorés', 'Erreurs'],
            $report->asRows(),
        );

        if ($report->errors !== []) {
            $this->newLine();
            $this->components->warn('Détail des erreurs (' . count($report->errors) . ') :');
            $this->table(
                ['Table', 'ID source', 'Message'],
                array_map(
                    static fn (array $e): array => [$e['table'], $e['id'], mb_substr($e['message'], 0, 80)],
                    array_slice($report->errors, 0, 50),
                ),
            );
            if (count($report->errors) > 50) {
                $this->line(sprintf('… et %d autres erreurs (tronquées).', count($report->errors) - 50));
            }
        }

        return self::SUCCESS;
    }
}
