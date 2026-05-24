<?php

declare(strict_types=1);

namespace App\Foundation\Exports\Formats;

use App\Foundation\Exports\ColumnDefinition;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

/**
 * Adapter class that turns an ExportBuilder + Eloquent Builder pair into a
 * maatwebsite/excel export. Callers never construct this directly — they
 * use ExportBuilder::toExcel().
 *
 * Implements:
 *   - FromQuery + WithChunkReading  : streams via 500-row chunks, no full hydration in memory
 *   - WithMapping                    : delegates per-cell value to ColumnDefinition
 *   - WithColumnFormatting           : applies date / decimal Excel formats
 *   - WithEvents                     : header styling (bold + brand colour) and frozen pane
 */
final class GenericExcelExport implements
    FromQuery,
    WithHeadings,
    WithMapping,
    WithTitle,
    WithChunkReading,
    WithColumnFormatting,
    WithEvents,
    ShouldAutoSize
{
    /** @param list<ColumnDefinition> $columns */
    public function __construct(
        private readonly Builder $query,
        private readonly array $columns,
        private readonly string $sheetTitle = 'Export',
    ) {}

    public function query(): Builder
    {
        return $this->query;
    }

    public function headings(): array
    {
        return array_map(static fn (ColumnDefinition $c): string => $c->header, $this->columns);
    }

    public function map($row): array
    {
        return array_map(
            static function (ColumnDefinition $c) use ($row): mixed {
                $value = $c->valueFor($row);
                // Excel epochs only — Carbon → serial number for proper date cells.
                if ($value instanceof \DateTimeInterface
                    && in_array($c->format, ['date', 'datetime'], true)) {
                    return ExcelDate::PHPToExcel($value);
                }
                return $value;
            },
            $this->columns,
        );
    }

    public function title(): string
    {
        return mb_substr($this->sheetTitle, 0, 31); // Excel sheet name cap
    }

    public function chunkSize(): int
    {
        return 500;
    }

    /** @return array<string, string> */
    public function columnFormats(): array
    {
        $map = [];
        foreach ($this->columns as $i => $c) {
            $letter = $this->columnLetter($i);
            $map[$letter] = match ($c->format) {
                'date'     => NumberFormat::FORMAT_DATE_YYYYMMDD,
                'datetime' => 'yyyy-mm-dd hh:mm',
                'decimal'  => '0.00',
                'integer'  => NumberFormat::FORMAT_NUMBER,
                'currency' => '#,##0 "FCFA"',
                default    => NumberFormat::FORMAT_TEXT,
            };
        }
        return $map;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $lastColumn = $this->columnLetter(count($this->columns) - 1);

                // Header row: bold white on brand blue.
                $sheet->getStyle("A1:{$lastColumn}1")->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                    'fill' => [
                        'fillType'   => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => '1D4ED8'],
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical'   => Alignment::VERTICAL_CENTER,
                    ],
                ]);
                $sheet->getRowDimension(1)->setRowHeight(24);
                $sheet->freezePane('A2');
            },
        ];
    }

    private function columnLetter(int $index): string
    {
        // 0 → A, 25 → Z, 26 → AA, ...
        $letter = '';
        $index++;
        while ($index > 0) {
            $index--;
            $letter = chr(65 + ($index % 26)) . $letter;
            $index = intdiv($index, 26);
        }
        return $letter;
    }
}
