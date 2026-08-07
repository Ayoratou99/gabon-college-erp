<?php

declare(strict_types=1);

namespace Modules\Concours\Services;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Modules\Concours\Models\Candidat;
use Modules\Concours\Models\Epreuve;
use Modules\Concours\Models\Note;

/**
 * Recomputes moyenne pondérée + rang for every paid candidate of a session.
 *
 *   moyenne = Σ (note × coefficient_epreuve) / Σ coefficient_epreuve
 *
 * Only counts non-null notes whose epreuve is applicable to the candidate.
 * Absent (absent=true) counts as 0. Candidates missing at least one
 * applicable note keep moyenne=null and are excluded from ranking.
 *
 * Ranking is computed per first-choice section (sorted by moyenne DESC).
 */
final class MoyenneCalculatorService
{
    public function __construct(
        private readonly ConnectionInterface $db,
    ) {}

    /** @return array{computed:int, ranked:int} */
    public function recomputeForSession(string $sessionId): array
    {
        $computed = 0;
        $ranked = 0;

        $this->db->transaction(function () use ($sessionId, &$computed, &$ranked): void {
            // Step 1: per-candidat moyenne
            $candidats = Candidat::query()
                ->where('concours_session_id', $sessionId)
                ->whereIn('statut', [Candidat::STATUS_VALID, Candidat::STATUS_ADMIS])
                ->get(['id', 'section_premier_choix_id', 'centre_id', 'concours_session_id']);

            foreach ($candidats as $candidat) {
                $applicableEpreuveIds = $this->epreuveIdsApplicableTo($candidat);
                if ($applicableEpreuveIds === []) {
                    $candidat->forceFill(['moyenne' => null, 'rang' => null, 'rang_centre' => null])->save();
                    continue;
                }

                $notes = Note::query()
                    ->where('candidat_id', $candidat->id)
                    ->whereIn('epreuve_id', $applicableEpreuveIds)
                    ->with('epreuve:id,coefficient')
                    ->get();

                // Need a note (entered or marked absent) for *every* applicable epreuve.
                if ($notes->count() < count($applicableEpreuveIds)) {
                    $candidat->forceFill(['moyenne' => null, 'rang' => null, 'rang_centre' => null])->save();
                    continue;
                }

                $totalCoef = 0.0;
                $totalPondere = 0.0;
                foreach ($notes as $note) {
                    $coef = (float) $note->epreuve->coefficient;
                    $val  = $note->absent ? 0.0 : (float) $note->valeur;
                    $totalCoef    += $coef;
                    $totalPondere += $val * $coef;
                }

                $moyenne = $totalCoef > 0 ? round($totalPondere / $totalCoef, 2) : null;
                $candidat->forceFill(['moyenne' => $moyenne])->save();
                $computed++;
            }

            // Step 2: rang per first-choice section (all centres combined) —
            // this is the competition ranking the selection is based on.
            $sections = $candidats->pluck('section_premier_choix_id')->unique()->filter();
            foreach ($sections as $sectionId) {
                $ranking = Candidat::query()
                    ->where('concours_session_id', $sessionId)
                    ->where('section_premier_choix_id', $sectionId)
                    ->whereNotNull('moyenne')
                    ->orderByDesc('moyenne')
                    ->orderBy('id') // stable tiebreak
                    ->get(['id']);

                foreach ($ranking as $i => $row) {
                    DB::table('candidats')->where('id', $row->id)->update(['rang' => $i + 1]);
                    $ranked++;
                }
            }

            // Step 3: rang WITHIN each centre, for the same section. This is
            // what a centre posts locally; it never drives the selection.
            $pairs = $candidats
                ->filter(fn ($c) => $c->section_premier_choix_id !== null && $c->centre_id !== null)
                ->map(fn ($c) => [$c->section_premier_choix_id, $c->centre_id])
                ->unique(fn (array $p): string => $p[0] . '|' . $p[1]);

            foreach ($pairs as [$sectionId, $centreId]) {
                $ranking = Candidat::query()
                    ->where('concours_session_id', $sessionId)
                    ->where('section_premier_choix_id', $sectionId)
                    ->where('centre_id', $centreId)
                    ->whereNotNull('moyenne')
                    ->orderByDesc('moyenne')
                    ->orderBy('id')
                    ->get(['id']);

                foreach ($ranking as $i => $row) {
                    DB::table('candidats')->where('id', $row->id)->update(['rang_centre' => $i + 1]);
                }
            }
        });

        return ['computed' => $computed, 'ranked' => $ranked];
    }

    /** @return array<int, string> */
    private function epreuveIdsApplicableTo(Candidat $candidat): array
    {
        $section = $candidat->section_premier_choix_id;
        if ($section === null) {
            return [];
        }

        // Applicable épreuves = those whose section list (epreuve_sections pivot)
        // includes the candidat's first-choice section.
        return Epreuve::query()
            ->where('concours_session_id', $candidat->concours_session_id)
            ->where('active', true)
            ->whereHas('sections', fn ($q) => $q->where('sections.id', $section))
            ->pluck('id')
            ->all();
    }
}
