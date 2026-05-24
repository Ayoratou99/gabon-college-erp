<?php

declare(strict_types=1);

namespace App\Foundation\Exports;

use App\Foundation\Exports\Exceptions\ExportFormatNotSupportedException;
use App\Foundation\Exports\Formats\CsvStreamer;
use App\Foundation\Exports\Formats\GenericExcelExport;
use App\Foundation\Exports\Formats\PdfRenderer;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Excel;
use Symfony\Component\HttpFoundation\Response;

/**
 * One fluent entry point for all tabular exports.
 *
 *     return ExportBuilder::for($query)
 *         ->columns(Candidat::exportColumns())   // or ->columnsFromModel(Candidat::class)
 *         ->title('Liste des candidats')
 *         ->meta(['Session' => $session->code])
 *         ->filename('candidats')
 *         ->download($request->get('format', 'xlsx'));
 *
 * The builder doesn't decide *where* the file goes — `download()` returns
 * a Symfony Response so the controller stays in charge.
 *
 * Supported formats: xlsx, csv, pdf.
 */
final class ExportBuilder
{
    public const SUPPORTED = ['xlsx', 'csv', 'pdf'];

    /** @var list<ColumnDefinition> */
    private array $columns = [];

    private string $title = 'Export';

    /** @var array<string, mixed> */
    private array $meta = [];

    private string $filenameBase = 'export';
    private string $pdfOrientation = 'landscape';

    private function __construct(
        private readonly Builder $query,
    ) {}

    public static function for(Builder $query): self
    {
        return new self($query);
    }

    /**
     * Accept raw model column definitions and normalise to ColumnDefinition VOs.
     *
     * @param  list<array<string, mixed>>  $columns
     */
    public function columns(array $columns): self
    {
        $this->columns = array_map(
            static fn (array $c): ColumnDefinition => ColumnDefinition::fromArray($c),
            $columns,
        );
        return $this;
    }

    /**
     * Pull columns from the model via the HasExportableColumns trait.
     *
     * @param  class-string  $model
     */
    public function columnsFromModel(string $model): self
    {
        if (! method_exists($model, 'exportColumns')) {
            throw new \LogicException("{$model} must declare a static exportColumns() method.");
        }
        $this->columns($model::exportColumns());

        if (method_exists($model, 'exportRelations')) {
            $relations = $model::exportRelations();
            if ($relations !== []) {
                $this->query->with($relations);
            }
        }
        return $this;
    }

    public function title(string $title): self
    {
        $this->title = $title;
        return $this;
    }

    /** @param array<string, mixed> $meta */
    public function meta(array $meta): self
    {
        $this->meta = $meta;
        return $this;
    }

    public function filename(string $base): self
    {
        // Sanitised lowercased ASCII slug, with date stamp appended by `download()`.
        $this->filenameBase = preg_replace('/[^a-z0-9\-_]+/i', '-', mb_strtolower($base)) ?: 'export';
        return $this;
    }

    public function landscape(): self
    {
        $this->pdfOrientation = 'landscape';
        return $this;
    }

    public function portrait(): self
    {
        $this->pdfOrientation = 'portrait';
        return $this;
    }

    public function download(string $format = 'xlsx'): Response
    {
        $format = mb_strtolower(trim($format));
        if (! in_array($format, self::SUPPORTED, true)) {
            throw ExportFormatNotSupportedException::for($format, self::SUPPORTED);
        }
        if ($this->columns === []) {
            throw new \LogicException('No columns declared on ExportBuilder. Call columns() or columnsFromModel().');
        }

        $filename = sprintf('%s-%s.%s', $this->filenameBase, now()->format('Ymd-His'), $format);

        return match ($format) {
            'xlsx' => $this->downloadExcel($filename),
            'csv'  => $this->downloadCsv($filename),
            'pdf'  => $this->downloadPdf($filename),
        };
    }

    private function downloadExcel(string $filename): Response
    {
        /** @var Excel $excel */
        $excel = app(Excel::class);
        return $excel->download(
            new GenericExcelExport($this->query, $this->columns, $this->title),
            $filename,
        );
    }

    private function downloadCsv(string $filename): Response
    {
        return (new CsvStreamer($this->query, $this->columns))
            ->streamedResponse($filename);
    }

    private function downloadPdf(string $filename): Response
    {
        return app(PdfRenderer::class)->render(
            $this->query,
            $this->columns,
            $this->title,
            $this->meta,
            $filename,
            $this->pdfOrientation,
        );
    }
}
