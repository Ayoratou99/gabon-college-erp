<?php

declare(strict_types=1);

namespace Modules\Concours\Services\Legacy;

/**
 * Carries the legacy_id → new_uuid maps across importers so a child
 * importer (documents, motifs, payments) can resolve its parent candidat
 * without a DB round-trip.
 *
 * Populated by the parent importer immediately after the parent row is
 * inserted. Cheap in-memory hash; OK because we're importing at most a
 * few thousand rows.
 */
final class LegacyImportContext
{
    /** @var array<int, string> legacy idetu → new candidat UUID */
    public array $candidatByLegacyId = [];

    /**
     * Full set of legacy idetu values that exist in the dump's etudiants
     * table (regardless of whether they were imported, deduped, or skipped).
     * Lets dependent importers distinguish "orphan reference" (idetu was
     * already gone in the source DB) from "should-be-importable but we
     * missed it" (idetu exists, but we don't have a candidat for it).
     *
     * @var array<int, true>
     */
    public array $legacyEtudiantIds = [];

    /** @var array<int, string> legacy idcent → new centre UUID */
    public array $centreByLegacyId = [];

    /** @var array<int, string> legacy idconc → new concours_session UUID */
    public array $sessionByLegacyId = [];

    /** @var array<int, string> legacy idut → new user UUID */
    public array $userByLegacyId = [];

    /** @var array<int, string> legacy idsect → new section UUID */
    public array $sectionByLegacyId = [];

    /** @var array<int, string> legacy idbac → new serie_bac UUID */
    public array $serieBacByLegacyId = [];

    /** @var array<int, string> legacy iddoc → new document_requis UUID */
    public array $documentByLegacyId = [];

    /** @var array<string, string> legacy nationalite NAME → new nationalite UUID */
    public array $nationaliteByLegacyName = [];
}
