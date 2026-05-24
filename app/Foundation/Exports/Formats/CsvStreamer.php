<?php

declare(strict_types=1);

namespace App\Foundation\Exports\Formats;

use App\Foundation\Exports\ColumnDefinition;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Memory-bound CSV writer: streams chunks straight into the HTTP response,
 * never holds the whole result set in memory.
 *
 *   - UTF-8 BOM (so Excel opens accented FR correctly without prompting)
 *   - RFC 4180 quoting
 *   - 500-row chunks
 *   - First row = headers
 */
final class CsvStreamer
{
    private const CHUNK_SIZE = 500;

    /** @param list<ColumnDefinition> $columns */
    public function __construct(
        private readonly Builder $query,
        private readonly array $columns,
    ) {}

    public function streamedResponse(string $filename): StreamedResponse
    {
        $callback = function (): void {
            $handle = fopen('php://output', 'wb');
            if ($handle === false) {
                throw new \RuntimeException('Cannot open php://output for CSV export.');
            }

            // UTF-8 BOM so Excel detects encoding correctly on Windows.
            fwrite($handle, "\xEF\xBB\xBF");

            // Header row.
            fputcsv(
                $handle,
                array_map(static fn (ColumnDefinition $c): string => $c->header, $this->columns),
                ',',
                '"',
                '\\',
            );

            $this->query->chunk(self::CHUNK_SIZE, function ($rows) use ($handle): void {
                foreach ($rows as $row) {
                    fputcsv(
                        $handle,
                        array_map(
                            static fn (ColumnDefinition $c): string => self::stringify($c->valueFor($row)),
                            $this->columns,
                        ),
                        ',',
                        '"',
                        '\\',
                    );
                }
            });

            fclose($handle);
        };

        return new StreamedResponse($callback, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => sprintf('attachment; filename="%s"', $filename),
            'Cache-Control'       => 'no-store, no-cache',
            'Pragma'              => 'no-cache',
        ]);
    }

    private static function stringify(mixed $value): string
    {
        if ($value === null) {
            return '';
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i');
        }
        if (is_bool($value)) {
            return $value ? 'Oui' : 'Non';
        }
        if (is_scalar($value)) {
            return (string) $value;
        }
        return json_encode($value, JSON_UNESCAPED_UNICODE) ?: '';
    }
}
