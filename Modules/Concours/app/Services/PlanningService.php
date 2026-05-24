<?php

declare(strict_types=1);

namespace Modules\Concours\Services;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Collection;
use Modules\Concours\DTOs\SchedulePlanningDto;
use Modules\Concours\Models\EpreuvePlanning;

/**
 * Schedules an épreuve at a centre. Conflict-checks against existing
 * plannings: same salle + overlapping time on the same date.
 *
 *   schedule(dto)             upsert (one planning per epreuve × centre).
 *   conflicts(planning)       returns colliding rows for UI feedback.
 *   planningForCandidat(c)    full epreuve schedule visible to the candidat.
 */
final class PlanningService
{
    public function __construct(
        private readonly ConnectionInterface $db,
    ) {}

    /**
     * @return array{planning: EpreuvePlanning, conflicts: Collection<int, EpreuvePlanning>}
     */
    public function schedule(SchedulePlanningDto $dto): array
    {
        return $this->db->transaction(function () use ($dto): array {
            /** @var EpreuvePlanning $planning */
            $planning = EpreuvePlanning::query()->updateOrCreate(
                [
                    'epreuve_id'                 => $dto->epreuveId,
                    'concours_session_centre_id' => $dto->concoursSessionCentreId,
                ],
                [
                    'salle_id'     => $dto->salleId,
                    'date_epreuve' => $dto->dateEpreuve,
                    'heure_debut' => $this->normaliseTime($dto->heureDebut),
                    'heure_fin'   => $this->normaliseTime($dto->heureFin),
                    'consigne'    => $dto->consigne,
                ],
            );

            return [
                'planning'  => $planning,
                'conflicts' => $this->conflicts($planning),
            ];
        });
    }

    /** @return Collection<int, EpreuvePlanning> */
    public function conflicts(EpreuvePlanning $candidate): Collection
    {
        if ($candidate->salle_id === null) {
            return collect();
        }

        return EpreuvePlanning::query()
            ->where('salle_id', $candidate->salle_id)
            ->where('date_epreuve', $candidate->date_epreuve)
            ->whereKeyNot($candidate->getKey())
            ->where(function ($q) use ($candidate): void {
                $q->where(function ($a) use ($candidate): void {
                    $a->where('heure_debut', '<', $candidate->heure_fin)
                      ->where('heure_fin', '>', $candidate->heure_debut);
                });
            })
            ->with(['epreuve:id,code,libelle', 'salle:id,nom'])
            ->get();
    }

    /**
     * Builds the candidate-facing schedule view.
     *
     * @return Collection<int, EpreuvePlanning>
     */
    public function planningForCandidat(\Modules\Concours\Models\Candidat $candidat): Collection
    {
        $section = $candidat->section_premier_choix_id;
        $cycle = \Modules\AcademicStructure\Models\Section::query()->where('id', $section)->value('cycle_id');
        $sessionCentre = \Modules\Concours\Models\ConcoursSession::query()
            ->where('id', $candidat->concours_session_id)
            ->with(['centres' => fn ($q) => $q->wherePivot('active', true)])
            ->first()
            ?->centres
            ?->firstWhere('id', $candidat->centre_id);

        if ($sessionCentre === null) {
            return collect();
        }

        return EpreuvePlanning::query()
            ->with(['epreuve.typeEpreuve', 'salle'])
            ->where('concours_session_centre_id', $sessionCentre->pivot->id)
            ->whereHas('epreuve', function ($q) use ($cycle, $section, $candidat): void {
                $q->where('concours_session_id', $candidat->concours_session_id)
                  ->where('active', true)
                  ->where(function ($inner) use ($cycle, $section): void {
                      $inner->where(function ($s) use ($section): void {
                          $s->where('scope_type', 'section')->where('scope_id', $section);
                      })->orWhere(function ($s) use ($cycle): void {
                          $s->where('scope_type', 'cycle')->where('scope_id', $cycle);
                      });
                  });
            })
            ->orderBy('date_epreuve')
            ->orderBy('heure_debut')
            ->get();
    }

    private function normaliseTime(string $hm): string
    {
        return strlen($hm) === 5 ? $hm . ':00' : $hm; // "08:30" → "08:30:00"
    }
}
