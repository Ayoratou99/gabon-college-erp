<?php

declare(strict_types=1);

namespace App\Foundation\Exports;

use Closure;

/**
 * Single export column.
 *
 *   header   — the title rendered in the file (sheet header row, PDF table th, CSV row 1).
 *   accessor — either a dotted attribute path ("centre.nom") or a Closure(row): mixed.
 *   format   — soft format hint: 'string' | 'integer' | 'decimal' | 'currency' | 'date'
 *              | 'datetime' | 'boolean'. Exporters use this for cell formatting; callers
 *              may always return any scalar.
 *   width    — optional auto-width hint for Excel (in characters), or column width
 *              fraction for PDF (1 = equal share). Ignored by CSV.
 *   align    — 'left' | 'center' | 'right' — affects Excel + PDF only.
 */
final readonly class ColumnDefinition
{
    public function __construct(
        public string $header,
        public string|Closure $accessor,
        public string $format = 'string',
        public ?int $width = null,
        public string $align = 'left',
    ) {}

    /**
     * Build from the array shape models declare via HasExportableColumns:
     *   ['header' => 'Nom', 'accessor' => 'nom']
     *   ['header' => 'Centre', 'accessor' => fn($c) => $c->centre?->nom, 'format' => 'string']
     *
     * @param  array<string, mixed>  $config
     */
    public static function fromArray(array $config): self
    {
        return new self(
            header:   (string) ($config['header'] ?? '?'),
            accessor: $config['accessor'] ?? '',
            format:   (string) ($config['format'] ?? 'string'),
            width:    isset($config['width']) ? (int) $config['width'] : null,
            align:    (string) ($config['align'] ?? 'left'),
        );
    }

    /**
     * Resolve the cell value for the given row.
     */
    public function valueFor(object $row): mixed
    {
        $raw = $this->resolveRaw($row);
        return $this->cast($raw);
    }

    private function resolveRaw(object $row): mixed
    {
        if ($this->accessor instanceof Closure) {
            return ($this->accessor)($row);
        }

        // Dotted path: "centre.nom" → $row->centre?->nom
        $value = $row;
        foreach (explode('.', $this->accessor) as $segment) {
            if ($value === null) {
                return null;
            }
            if (is_array($value)) {
                $value = $value[$segment] ?? null;
                continue;
            }
            $value = $value->$segment ?? null;
        }
        return $value;
    }

    private function cast(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($this->format) {
            'date'     => $value instanceof \DateTimeInterface ? $value->format('Y-m-d') : (string) $value,
            'datetime' => $value instanceof \DateTimeInterface ? $value->format('Y-m-d H:i') : (string) $value,
            'integer'  => is_numeric($value) ? (int) $value : $value,
            'decimal'  => is_numeric($value) ? (float) $value : $value,
            'boolean'  => $value ? 'Oui' : 'Non',
            default    => $value,
        };
    }
}
