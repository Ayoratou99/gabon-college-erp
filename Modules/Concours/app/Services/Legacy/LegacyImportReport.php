<?php

declare(strict_types=1);

namespace Modules\Concours\Services\Legacy;

/**
 * Mutable, in-memory tally tracked across every importer.
 *
 *   counts[table] = ['imported' => int, 'skipped' => int, 'errors' => int]
 *   errors        = list<[table, source_id, message]>
 *
 * The command renders both at the end as ASCII tables.
 */
final class LegacyImportReport
{
    /** @var array<string, array{imported:int, skipped:int, errors:int}> */
    public private(set) array $counts = [];

    /** @var list<array{table:string, id:string, message:string}> */
    public private(set) array $errors = [];

    public function importedOne(string $table): void
    {
        $this->touch($table);
        $this->counts[$table]['imported']++;
    }

    public function skippedOne(string $table): void
    {
        $this->touch($table);
        $this->counts[$table]['skipped']++;
    }

    public function failedOne(string $table, string $sourceId, string $message): void
    {
        $this->touch($table);
        $this->counts[$table]['errors']++;
        $this->errors[] = ['table' => $table, 'id' => $sourceId, 'message' => $message];
    }

    /** @return array<int, array{0:string, 1:int, 2:int, 3:int}> rows for renderTable */
    public function asRows(): array
    {
        $rows = [];
        foreach ($this->counts as $table => $c) {
            $rows[] = [$table, $c['imported'], $c['skipped'], $c['errors']];
        }
        return $rows;
    }

    private function touch(string $table): void
    {
        $this->counts[$table] ??= ['imported' => 0, 'skipped' => 0, 'errors' => 0];
    }
}
