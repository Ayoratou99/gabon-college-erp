<?php

declare(strict_types=1);

namespace Modules\Concours\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Modules\AcademicStructure\Models\AnneeAcademique;
use Modules\Concours\Models\ConcoursSession;
use Modules\Concours\Models\ResultPublication;

/**
 * Brings forward result PDFs published under the legacy PHP system. The old
 * site only ever exposed a single yearly PDF (e.g. resultats/res_CUK_2025.pdf)
 * — there were no per-candidate winner records. We mirror that by creating:
 *
 *   - a closed ConcoursSession placeholder per legacy year
 *   - a ResultPublication row whose `fichier_path` points at the copied PDF
 *
 * The PDF is copied from this seeder directory into storage/app/public so
 * the public `/resultats/{session}/telecharger` route can stream it.
 *
 * Idempotent: safe to re-run; existing rows are detected and skipped.
 */
final class HistoricalResultsSeeder extends Seeder
{
    /**
     * @var list<array{
     *     session_code: string,
     *     session_libelle: string,
     *     annee_code: string,
     *     date_concours: string,
     *     pdf: string,
     *     total_candidats: int,
     *     total_admis: int,
     *     communique: string,
     * }>
     */
    private const ENTRIES = [
        [
            'session_code'    => 'CONCOURS-2024-2025-LEGACY',
            'session_libelle' => 'Concours d\'entrée — session 2024-2025 (archive)',
            'annee_code'      => '2024-2025',
            'date_concours'   => '2025-08-14',
            'pdf'             => 'res_CUK_2025.pdf',
            'total_candidats' => 0, // unknown for archive-only sessions
            'total_admis'     => 0,
            'communique'      => "Liste officielle des admis au concours d'entrée 2024-2025. "
                                 . "Cette session est archivée — consultez le PDF ci-dessous "
                                 . "pour la liste complète.",
        ],
    ];

    public function run(): void
    {
        $sourceDir = __DIR__ . '/historical';
        $disk      = Storage::disk('public');

        foreach (self::ENTRIES as $entry) {
            // Resolve the academic year (must exist).
            $annee = AnneeAcademique::query()->where('code', $entry['annee_code'])->first();
            if ($annee === null) {
                $this->command?->warn("HistoricalResults: année académique {$entry['annee_code']} introuvable, skip.");
                continue;
            }

            // Create or fetch the placeholder session for that year.
            $session = ConcoursSession::query()->updateOrCreate(
                ['code' => $entry['session_code']],
                [
                    'annee_academique_id'         => $annee->id,
                    'libelle'                     => $entry['session_libelle'],
                    'date_ouverture_inscriptions' => $entry['date_concours'],
                    'date_fermeture_inscriptions' => $entry['date_concours'],
                    'date_concours'               => $entry['date_concours'],
                    'statut'                      => 'clos',
                    'est_active'                  => false,
                ],
            );

            // Stash the PDF under storage/app/public/historical-results/.
            $sourcePath = $sourceDir . '/' . $entry['pdf'];
            if (! is_file($sourcePath)) {
                $this->command?->warn("HistoricalResults: PDF {$entry['pdf']} introuvable, skip publication.");
                continue;
            }

            $storedPath = 'historical-results/' . $entry['pdf'];
            if (! $disk->exists($storedPath)) {
                $disk->put($storedPath, file_get_contents($sourcePath));
            }

            // One ResultPublication per legacy session, pointing at the PDF.
            ResultPublication::query()->updateOrCreate(
                ['concours_session_id' => $session->id],
                [
                    'published_by_user_id'  => null,
                    'published_at'          => $session->date_concours,
                    'total_candidats'       => $entry['total_candidats'],
                    'total_admis'           => $entry['total_admis'],
                    'breakdown_par_section' => [],
                    'fichier_path'          => $storedPath,
                    'fichier_disk'          => 'public',
                    'communique'            => $entry['communique'],
                    'active'                => true,
                ],
            );

            $this->command?->info("HistoricalResults: imported {$entry['session_code']} ← {$entry['pdf']}.");
        }
    }
}
