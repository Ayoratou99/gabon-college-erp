<?php

declare(strict_types=1);

namespace Modules\Concours\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Modules\AcademicStructure\Models\Section;
use Modules\Concours\Models\Candidat;
use Modules\Concours\Models\ConcoursSession;

/**
 * Excel workbook of the concours winners ("gagnants"), one worksheet per
 * section of orientation — that is the grouping the jury signs off on.
 *
 * Source of truth is the published selection: candidats flipped to `admis`
 * with an orientation, so the file always mirrors what was actually
 * published rather than a transient screen state.
 */
final class GagnantsExport implements WithMultipleSheets
{
    public function __construct(
        private readonly ConcoursSession $session,
        private readonly bool $includeSecretId = false,
    ) {}

    /** @return array<int, GagnantsSectionSheet> */
    public function sheets(): array
    {
        $admis = Candidat::query()
            ->where('concours_session_id', $this->session->getKey())
            ->where('statut', Candidat::STATUS_ADMIS)
            ->with(['centre:id,nom', 'sectionOrientation:id,code,nom'])
            ->orderBy('rang')
            ->orderBy('nom')
            ->get();

        /** @var Collection<string, Collection<int, Candidat>> $grouped */
        $grouped = $admis->groupBy(fn (Candidat $c): string => (string) $c->section_orientation_id);

        $sections = Section::query()
            ->whereIn('id', $grouped->keys()->filter()->all())
            ->orderBy('nom')
            ->get(['id', 'code', 'nom'])
            ->keyBy('id');

        $sheets = [];
        foreach ($sections as $id => $section) {
            $sheets[] = new GagnantsSectionSheet(
                label:           trim(($section->code ?? '') . ' ' . $section->nom) ?: 'Section',
                rows:            $grouped->get((string) $id, collect())->values(),
                includeSecretId: $this->includeSecretId,
            );
        }

        // Admis without an orientation would otherwise vanish from the file.
        $orphans = $grouped->get('', collect());
        if ($orphans->isNotEmpty()) {
            $sheets[] = new GagnantsSectionSheet(
                label:           'Sans orientation',
                rows:            $orphans->values(),
                includeSecretId: $this->includeSecretId,
            );
        }

        return $sheets;
    }
}
