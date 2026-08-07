<?php

declare(strict_types=1);

namespace Modules\Concours\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use Modules\Concours\Models\Candidat;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * One section's winners. Sheet title is the section label, capped to Excel's
 * 31-character limit and stripped of the characters Excel forbids.
 */
final class GagnantsSectionSheet implements
    FromCollection,
    WithHeadings,
    WithMapping,
    WithTitle,
    WithEvents,
    ShouldAutoSize
{
    /** @param Collection<int, Candidat> $rows */
    public function __construct(
        private readonly string $label,
        private readonly Collection $rows,
        private readonly bool $includeSecretId = false,
    ) {}

    public function collection(): Collection
    {
        return $this->rows;
    }

    /** @return list<string> */
    public function headings(): array
    {
        $headings = ['Rang', 'Rang centre', 'Matricule', 'Nom', 'Prénom', 'Sexe', 'Centre', 'Moyenne', 'Orientation'];
        if ($this->includeSecretId) {
            array_unshift($headings, 'Identifiant secret');
        }

        return $headings;
    }

    /**
     * @param  Candidat  $row
     * @return list<mixed>
     */
    public function map($row): array
    {
        $cells = [
            $row->rang,
            $row->rang_centre,
            $row->matricule_public,
            $row->nom,
            $row->prenom,
            $row->sexe,
            $row->centre?->nom,
            $row->moyenne === null ? null : (float) $row->moyenne,
            $row->sectionOrientation?->nom,
        ];
        if ($this->includeSecretId) {
            array_unshift($cells, $row->identifiant_secret);
        }

        return $cells;
    }

    public function title(): string
    {
        // Excel rejects : \ / ? * [ ] in sheet names and caps them at 31 chars.
        $clean = preg_replace('/[:\\\\\/?*\[\]]/', '-', $this->label) ?? 'Section';

        return mb_substr(trim($clean), 0, 31) ?: 'Section';
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet     = $event->sheet->getDelegate();
                $lastCol   = $this->includeSecretId ? 'J' : 'I';
                $lastRow   = max(2, $sheet->getHighestRow());

                $sheet->getStyle("A1:{$lastCol}1")->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '15803D']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);
                $sheet->getRowDimension(1)->setRowHeight(22);
                $sheet->freezePane('A2');

                if ($this->includeSecretId) {
                    // Confidential column — bold red, same convention as the
                    // candidat export.
                    $sheet->getStyle("A2:A{$lastRow}")->applyFromArray([
                        'font' => ['bold' => true, 'color' => ['rgb' => 'C00000']],
                    ]);
                    $sheet->getStyle('A1')->applyFromArray([
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'C00000']],
                    ]);
                }
            },
        ];
    }
}
