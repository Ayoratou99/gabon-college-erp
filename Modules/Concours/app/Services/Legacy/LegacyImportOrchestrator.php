<?php

declare(strict_types=1);

namespace Modules\Concours\Services\Legacy;

use Modules\UserManagement\Services\LegacyDumpParser;

/**
 * Coordinates the per-table importers in FK-safe order:
 *
 *   1. concours_sessions  (parent of candidats + payments)
 *   2. candidats          (parent of documents + motifs + payments)
 *   3. candidat_documents
 *   4. candidat_motifs_rejet
 *   5. payments
 *
 * Each step receives the same LegacyImportContext + Report — so a child
 * importer can resolve its parent via the maps the previous step populated.
 */
final class LegacyImportOrchestrator
{
    /** Tables runnable individually via --tables=... */
    public const TABLES = [
        'concours_sessions',
        'candidats',
        'candidat_documents',
        'candidat_motifs_rejet',
        'payments',
    ];

    public function __construct(
        private readonly LegacyConcoursImporter $sessions,
        private readonly LegacyCandidatImporter $candidats,
        private readonly LegacyDocumentImporter $documents,
        private readonly LegacyMotifImporter $motifs,
        private readonly LegacyPaymentImporter $payments,
    ) {}

    /**
     * @param  list<string>  $tables   subset of self::TABLES; empty = all
     */
    public function run(LegacyDumpParser $parser, array $tables, bool $dryRun): LegacyImportReport
    {
        $report = new LegacyImportReport();
        $ctx    = new LegacyImportContext();
        $tables = $tables === [] ? self::TABLES : $tables;

        // Always rebuild the candidat-id map from already-imported rows so
        // dependent tables can run independently on a re-run.
        $this->primeContext($ctx);

        $steps = [
            'concours_sessions'     => fn () => $this->sessions->import($parser, $ctx, $report, $dryRun),
            'candidats'             => fn () => $this->candidats->import($parser, $ctx, $report, $dryRun),
            'candidat_documents'    => fn () => $this->documents->import($parser, $ctx, $report, $dryRun),
            'candidat_motifs_rejet' => fn () => $this->motifs->import($parser, $ctx, $report, $dryRun),
            'payments'              => fn () => $this->payments->import($parser, $ctx, $report, $dryRun),
        ];

        foreach ($steps as $name => $step) {
            if (in_array($name, $tables, true)) {
                $step();
            }
        }

        return $report;
    }

    private function primeContext(LegacyImportContext $ctx): void
    {
        \Modules\Concours\Models\Candidat::query()
            ->whereNotNull('legacy_id')
            ->get(['id', 'legacy_id'])
            ->each(fn ($c) => $ctx->candidatByLegacyId[(int) $c->legacy_id] = (string) $c->id);

        \Modules\Concours\Models\ConcoursSession::query()
            ->whereNotNull('legacy_id')
            ->get(['id', 'legacy_id'])
            ->each(fn ($s) => $ctx->sessionByLegacyId[(int) $s->legacy_id] = (string) $s->id);
    }
}
