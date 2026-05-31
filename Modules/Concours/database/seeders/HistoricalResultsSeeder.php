<?php

declare(strict_types=1);

namespace Modules\Concours\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Modules\Concours\Models\Candidat;
use Modules\Concours\Models\ConcoursSession;
use Modules\Concours\Models\ResultPublication;

/**
 * Publishes the legacy yearly results PDF (procès-verbal) on the public
 * /resultats page. The old PHP site only ever exposed a single yearly PDF
 * (resultats/res_CUK_2025.pdf) — there were no per-candidate winner records —
 * so we mirror that with one ResultPublication whose `fichier_path` points at
 * the copied PDF.
 *
 * IMPORTANT: this seeder attaches the publication to an EXISTING session
 * (the kept legacy session). It must NOT create or mutate a session — an
 * earlier version created a separate `CONCOURS-2024-2025-LEGACY` placeholder,
 * which was later removed during session de-duplication, taking the PV with
 * it. We now bind to whatever real legacy session is present.
 *
 * The PDF is copied into storage/app/public/historical-results/ and streamed
 * server-side by CandidatDashboardController::resultsDownload (no public URL,
 * so storage:link is not required for the download to work).
 *
 * Idempotent: re-running updates the same publication row in place.
 */
final class HistoricalResultsSeeder extends Seeder
{
    /**
     * @var list<array{session_codes: list<string>, pdf: string, communique: string}>
     */
    private const ENTRIES = [
        [
            // Preferred target first; fall back to the active session if the
            // codes don't match (keeps the seeder resilient across renames).
            'session_codes' => ['CONCOURS-LEGACY-2025', 'CONCOURS-2025'],
            'pdf'           => 'res_CUK_2025.pdf',
            'communique'    => "Procès-verbal officiel du concours d'entrée — session 2025. "
                             . "Téléchargez le PDF ci-dessous pour la liste complète des admis.",
        ],
    ];

    public function run(): void
    {
        $disk = Storage::disk('public');

        foreach (self::ENTRIES as $entry) {
            // Bind to an EXISTING session — never create/modify one here.
            $session = null;
            foreach ($entry['session_codes'] as $code) {
                $session = ConcoursSession::query()->where('code', $code)->first();
                if ($session !== null) {
                    break;
                }
            }
            $session ??= ConcoursSession::active();

            if ($session === null) {
                $this->command?->warn('HistoricalResults: aucune session cible trouvée, skip.');
                continue;
            }

            // The PV is committed directly under storage/app/public/historical-results/,
            // so it's present after a `git pull` (the DB dump never carries storage
            // files). No copy needed — we just verify and reference it.
            $storedPath = 'historical-results/' . $entry['pdf'];
            if (! $disk->exists($storedPath)) {
                $this->command?->warn("HistoricalResults: PV introuvable à storage/app/public/{$storedPath} — déposez le PDF puis relancez. Skip.");
                continue;
            }

            ResultPublication::query()->updateOrCreate(
                ['concours_session_id' => $session->id],
                [
                    'published_by_user_id'  => null,
                    'published_at'          => $session->date_concours,
                    'total_candidats'       => Candidat::query()->where('concours_session_id', $session->id)->count(),
                    'total_admis'           => Candidat::query()->where('concours_session_id', $session->id)
                                                   ->where('statut', Candidat::STATUS_ADMIS)->count(),
                    'breakdown_par_section' => [],
                    'fichier_path'          => $storedPath,
                    'fichier_disk'          => 'public',
                    'communique'            => $entry['communique'],
                    'active'                => true,
                ],
            );

            $this->command?->info("HistoricalResults: publication attachée à {$session->code} ← {$entry['pdf']}.");
        }
    }
}
