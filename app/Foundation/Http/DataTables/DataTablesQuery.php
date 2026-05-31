<?php

declare(strict_types=1);

namespace App\Foundation\Http\DataTables;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Server-side handler for the DataTables.net AJAX protocol.
 *
 * Usage in a controller:
 *
 *     return DataTablesQuery::for(Candidat::query()->with('centre'))
 *         ->searchable(['nom', 'prenom', 'matricule_public', 'email'])
 *         ->orderable([
 *             'matricule_public' => 'matricule_public',
 *             'nom'              => 'nom',
 *             'centre'           => 'centre.nom',
 *         ])
 *         ->transform(fn (Candidat $c) => [
 *             'id'        => $c->id,
 *             'matricule' => $c->matricule_public,
 *             'nom'       => "{$c->nom} {$c->prenom}",
 *             'centre'    => $c->centre?->nom ?? '—',
 *         ])
 *         ->respond($request);
 */
final class DataTablesQuery
{
    /** @var list<string> */
    private array $searchable = [];

    /** @var array<string, string> column-name (sent by JS) => SQL column / dotted relation column */
    private array $orderable = [];

    /** @var Closure|null */
    private ?Closure $transformer = null;

    /** @var Closure|null  Hook to apply controller-supplied filter object. */
    private ?Closure $filterApplier = null;

    private function __construct(private readonly Builder $query) {}

    public static function for(Builder $query): self
    {
        return new self($query);
    }

    /** @param list<string> $columns */
    public function searchable(array $columns): self
    {
        $this->searchable = $columns;
        return $this;
    }

    /** @param array<string, string> $map  DT column name → SQL/relation column. */
    public function orderable(array $map): self
    {
        $this->orderable = $map;
        return $this;
    }

    /** @param Closure(mixed): array $fn */
    public function transform(Closure $fn): self
    {
        $this->transformer = $fn;
        return $this;
    }

    /** @param Closure(Builder, array): void $fn */
    public function filterUsing(Closure $fn): self
    {
        $this->filterApplier = $fn;
        return $this;
    }

    public function respond(Request $request): JsonResponse
    {
        $draw   = (int) $request->input('draw', 1);
        $start  = max(0, (int) $request->input('start', 0));
        $length = (int) $request->input('length', 25);
        $length = $length < 0 ? 1000 : min(max($length, 1), 500); // -1 means "all" in DT, cap it

        $searchValue = trim((string) $request->input('search.value', ''));

        // Total before any filtering — count uses a cloned query so eager loads
        // don't blow up the count.
        $totalQuery   = clone $this->query;
        $recordsTotal = $this->countQuery($totalQuery);

        // Apply controller filters (status, centre, etc.) before search so they
        // affect the filtered count.
        if ($this->filterApplier !== null) {
            $filters = (array) $request->input('filters', []);
            ($this->filterApplier)($this->query, $filters);
        }

        if ($searchValue !== '' && $this->searchable !== []) {
            $this->query->where(function (Builder $q) use ($searchValue): void {
                foreach ($this->searchable as $col) {
                    // Use ilike for Postgres; falls back to LIKE on other drivers.
                    $op = $q->getConnection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
                    $q->orWhere($col, $op, "%{$searchValue}%");
                }
            });
        }

        $recordsFiltered = $this->countQuery(clone $this->query);

        // Ordering.
        $orderColIdx = (int) $request->input('order.0.column', 0);
        $orderDir    = $request->input('order.0.dir', 'asc') === 'desc' ? 'desc' : 'asc';
        $colName     = (string) $request->input("columns.{$orderColIdx}.data", '');

        if ($colName !== '' && isset($this->orderable[$colName])) {
            $this->query->reorder($this->orderable[$colName], $orderDir);
        }

        $rows = $this->query->skip($start)->take($length)->get();

        $data = $this->transformer === null
            ? $rows->toArray()
            : $rows->map($this->transformer)->all();

        return response()->json([
            'draw'            => $draw,
            'recordsTotal'    => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data'            => $data,
        ]);
    }

    private function countQuery(Builder $q): int
    {
        // Drop eager-loads & ordering to make COUNT cheap and safe.
        $q->setEagerLoads([])->getQuery()->orders = null;
        return $q->toBase()->getCountForPagination();
    }
}
