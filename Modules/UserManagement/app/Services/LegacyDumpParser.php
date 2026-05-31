<?php

declare(strict_types=1);

namespace Modules\UserManagement\Services;

/**
 * Tiny SQL-dump reader for the legacy MariaDB export.
 *
 * Scope: extract VALUES tuples from `INSERT INTO <table> ...` blocks. The
 * dump is phpMyAdmin output and follows a predictable shape, so we don't
 * need a full SQL parser — `str_getcsv()` handles the per-row tuple cleanly
 * once we have the raw `(...)` chunk.
 *
 * Returns each row as an associative array keyed by the column names.
 */
final class LegacyDumpParser
{
    public function __construct(
        private readonly string $sql,
    ) {}

    public static function fromFile(string $path): self
    {
        if (! is_file($path)) {
            throw new \RuntimeException("Legacy dump not found at: {$path}");
        }
        return new self((string) file_get_contents($path));
    }

    /**
     * @return list<array<string, string|null>>
     */
    public function rowsOf(string $table): array
    {
        // INSERT INTO `utilisateurs` (`col1`,`col2`,...) VALUES (..), (..), ...;
        // NOTE: PCRE `U` flag *inverts* greediness, so .+? becomes greedy and
        // would gobble across the entire file. We deliberately use `.+?` with
        // `ms` only — ungreedy as written — and rely on the trailing `;\s*$`
        // (multiline anchor) to stop at the end of each INSERT block.
        $pattern = sprintf(
            '/INSERT\s+INTO\s+`%s`\s*\(([^)]+)\)\s*VALUES\s*(.+?);\s*$/ims',
            preg_quote($table, '/'),
        );

        $rows = [];

        if (preg_match_all($pattern, $this->sql, $matches, PREG_SET_ORDER) === false) {
            return $rows;
        }

        foreach ($matches as $match) {
            $columns = array_map(
                static fn (string $c): string => trim($c, " `\t\n\r"),
                explode(',', $match[1]),
            );

            foreach ($this->splitTuples($match[2]) as $tuple) {
                $values = $this->parseValueTuple($tuple);
                if (count($values) !== count($columns)) {
                    continue; // skip malformed lines instead of throwing — defensive
                }
                $rows[] = array_combine($columns, $values);
            }
        }

        return $rows;
    }

    /**
     * Split a "(a,b,c),(d,e,f)" string into its tuples, respecting quoted
     * strings (so commas inside 'foo, bar' don't fool us).
     *
     * @return list<string>
     */
    private function splitTuples(string $values): array
    {
        $tuples = [];
        $buffer = '';
        $depth = 0;
        $inSingle = false;
        $escape = false;

        $length = strlen($values);
        for ($i = 0; $i < $length; $i++) {
            $ch = $values[$i];

            if ($escape) {
                $buffer .= $ch;
                $escape = false;
                continue;
            }
            if ($ch === '\\' && $inSingle) {
                $buffer .= $ch;
                $escape = true;
                continue;
            }
            if ($ch === "'") {
                $inSingle = ! $inSingle;
                $buffer .= $ch;
                continue;
            }
            if (! $inSingle) {
                if ($ch === '(') {
                    $depth++;
                    if ($depth === 1) {
                        $buffer = '';
                        continue;
                    }
                } elseif ($ch === ')') {
                    $depth--;
                    if ($depth === 0) {
                        $tuples[] = $buffer;
                        $buffer = '';
                        continue;
                    }
                }
            }
            if ($depth > 0) {
                $buffer .= $ch;
            }
        }

        return $tuples;
    }

    /**
     * @return list<string|null>
     */
    private function parseValueTuple(string $tuple): array
    {
        // str_getcsv handles quoted strings and embedded commas; the
        // dump uses single-quotes so we set enclosure accordingly.
        $raw = str_getcsv($tuple, ',', "'", '\\');
        return array_map(
            static fn (string $v): ?string => trim($v) === 'NULL' ? null : trim($v),
            $raw,
        );
    }
}
