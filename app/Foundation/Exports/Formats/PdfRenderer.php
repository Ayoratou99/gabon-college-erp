<?php

declare(strict_types=1);

namespace App\Foundation\Exports\Formats;

use App\Foundation\Exports\ColumnDefinition;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\Response;

/**
 * Renders the ExportBuilder payload through a single Blade template
 * (`resources/views/exports/pdf.blade.php`) and streams the dompdf-generated
 * PDF as an HTTP download.
 *
 * For very large datasets (>2-3k rows) prefer the streamed Excel/CSV
 * formats — dompdf is happy with a few hundred rows but allocates everything
 * in memory and gets slow past ~1 MB of HTML.
 */
final class PdfRenderer
{
    private const HARD_ROW_CAP = 5000;

    public function __construct(
        private readonly ViewFactory $views,
    ) {}

    /**
     * @param  list<ColumnDefinition>  $columns
     * @param  array<string, mixed>     $meta
     */
    public function render(
        Builder $query,
        array $columns,
        string $title,
        array $meta,
        string $filename,
        string $orientation = 'landscape',
    ): Response {
        $rows = [];
        $count = 0;

        $query->chunk(500, function ($chunk) use ($columns, &$rows, &$count): void {
            foreach ($chunk as $row) {
                if ($count >= self::HARD_ROW_CAP) {
                    return;
                }
                $rows[] = array_map(
                    static fn (ColumnDefinition $c): mixed => $c->valueFor($row),
                    $columns,
                );
                $count++;
            }
        });

        $html = $this->views->make('exports.pdf', [
            'title'    => $title,
            'meta'     => $meta,
            'columns'  => $columns,
            'rows'     => $rows,
            'truncated'=> $count >= self::HARD_ROW_CAP,
            'generatedAt' => now(),
        ])->render();

        $pdf = Pdf::loadHTML($html)
            ->setPaper('a4', $orientation)
            ->setOptions(['isRemoteEnabled' => false, 'defaultFont' => 'DejaVu Sans']);

        return $pdf->download($filename);
    }
}
