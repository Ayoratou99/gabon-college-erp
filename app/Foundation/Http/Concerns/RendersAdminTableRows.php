<?php

declare(strict_types=1);

namespace App\Foundation\Http\Concerns;

use Illuminate\Database\Eloquent\Model;

/**
 * Shared row-formatter for registry-backed admin DataTables.
 *
 * Each column definition is `['data' => 'key', 'label' => '…']` plus optional
 * `'render' => fn (Model $row) => string` to handle relations / formatting.
 * Default rendering is per-type:
 *   bool   → "Oui" / "Non" badge
 *   array  → comma-joined inside <code>
 *   null   → "—"
 *   other  → escaped string
 */
trait RendersAdminTableRows
{
    /**
     * @param  list<array<string, mixed>>  $columns
     * @return array<string, mixed>
     */
    protected function renderRow(array $columns, Model $row): array
    {
        $out = ['id' => $row->getKey()];

        foreach ($columns as $col) {
            $name   = $col['data'];
            $render = $col['render'] ?? null;

            if (is_callable($render)) {
                $out[$name] = $render($row);
                continue;
            }

            $val = $row->getAttribute($name);
            $out[$name] = $this->defaultCell($val);
        }

        return $out;
    }

    private function defaultCell(mixed $val): string
    {
        return match (true) {
            is_bool($val) => $val
                ? '<span class="badge bg-success-subtle text-success-emphasis">Oui</span>'
                : '<span class="badge bg-secondary-subtle text-secondary-emphasis">Non</span>',
            is_array($val) => '<code class="small">' . e(implode(', ', $val)) . '</code>',
            is_null($val)  => '—',
            default        => e((string) $val),
        };
    }
}
