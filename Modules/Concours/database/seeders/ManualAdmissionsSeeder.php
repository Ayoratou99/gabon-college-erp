<?php

declare(strict_types=1);

namespace Modules\Concours\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\AcademicStructure\Models\Section;
use Modules\Concours\Models\Candidat;
use Modules\Concours\Models\ConcoursSession;
use Modules\Concours\Models\Centre;
use Modules\Referentiels\Models\Nationalite;
use Modules\Referentiels\Models\SerieBac;

/**
 * Closes the last gaps in the 2025 admission list (the 4 names
 * LegacyAdmissionResultsSeeder could not auto-match), so every section reaches
 * its full procès-verbal count.
 *
 * Two cases:
 *   1. MARK_EXISTING — the PV name has a single, unambiguous near-match already
 *      in the DB (unique family name, registered for that section). We flip
 *      THAT dossier to admis so the real candidat sees "Vous êtes admis(e)" on
 *      their own matricule. A note records the manual reconciliation.
 *   2. FAKE_ADMIS — the PV name never registered online (confirmed absent). We
 *      create a flagged "ajouté manuellement" admis dossier so they appear in
 *      the results + counts. Placeholder identity (email/tel @cuk.local, sexe
 *      'M', DOB 2000-01-01) — the admin can complete it. These are NOT online
 *      registrations and carry an `admission_note`, surfaced as a badge.
 *
 * Idempotent: MARK_EXISTING updates in place; FAKE_ADMIS upserts on matricule.
 */
final class ManualAdmissionsSeeder extends Seeder
{
    private const SESSION_CODES = ['CONCOURS-LEGACY-2025', 'CONCOURS-2025'];

    /** @var list<array{nom_like: string, section: string, pv: string}> */
    private const MARK_EXISTING = [
        ['nom_like' => 'BOUPENDZA YIMI%', 'section' => 'AEC', 'pv' => 'BOUPENDZA Aleron Wesley'],
        ['nom_like' => 'MENZUI OBAME%',   'section' => 'GTE', 'pv' => 'MENZUI OBAME Lyla'],
    ];

    /** @var list<array{key: string, nom: string, prenom: string, sexe: string, section: string}> */
    private const FAKE_ADMIS = [
        ['key' => 'PV01', 'nom' => 'LOUNDOU',       'prenom' => 'Edifa Braël',     'sexe' => 'M', 'section' => 'CI'],
        ['key' => 'PV02', 'nom' => 'OFOUNDA MEZUI', 'prenom' => 'Jordan Celestin', 'sexe' => 'M', 'section' => 'IC'],
    ];

    public function run(): void
    {
        $session = null;
        foreach (self::SESSION_CODES as $code) {
            $session = ConcoursSession::query()->where('code', $code)->first();
            if ($session !== null) {
                break;
            }
        }
        $session ??= ConcoursSession::active();
        if ($session === null) {
            $this->command?->warn('ManualAdmissions: aucune session cible — skip.');

            return;
        }

        /** @var array<string, Section> $sectionsByCode */
        $sectionsByCode = Section::query()->get()->keyBy('code')->all();

        // ---- 1. Reconcile existing near-matches ----
        foreach (self::MARK_EXISTING as $e) {
            $section = $sectionsByCode[$e['section']] ?? null;
            if ($section === null) {
                $this->command?->warn("ManualAdmissions: section {$e['section']} introuvable — skip.");
                continue;
            }

            $rows = Candidat::query()
                ->where('concours_session_id', $session->id)
                ->where('nom', 'ilike', $e['nom_like'])
                ->get();

            if ($rows->count() !== 1) {
                $this->command?->warn(
                    "ManualAdmissions: « {$e['pv']} » → {$rows->count()} correspondance(s) pour « {$e['nom_like']} », "
                    . 'rapprochement manuel requis — skip.'
                );
                continue;
            }

            /** @var Candidat $c */
            $c = $rows->first();
            $c->forceFill([
                'statut'                 => Candidat::STATUS_ADMIS,
                'section_orientation_id' => $section->id,
                'admis_at'               => $c->admis_at ?? now(),
                'admission_note'         => "Admis rapproché manuellement du procès-verbal 2025 (« {$e['pv']} »).",
            ])->save();

            $this->command?->info("ManualAdmissions: {$c->nom} {$c->prenom} → admis {$e['section']} (existant).");
        }

        // ---- 2. Flagged manual dossiers for never-registered PV admis ----
        $natId    = Nationalite::query()->where('nom', 'ilike', '%gabon%')->value('id') ?? Nationalite::query()->value('id');
        $serieId  = SerieBac::query()->value('id');
        $centreId = Centre::query()->where('active', true)->value('id') ?? Centre::query()->value('id');

        if ($natId === null || $serieId === null || $centreId === null) {
            $this->command?->warn('ManualAdmissions: référentiels (nationalité/série/centre) manquants — fakes ignorés.');

            return;
        }

        foreach (self::FAKE_ADMIS as $f) {
            $section = $sectionsByCode[$f['section']] ?? null;
            if ($section === null) {
                $this->command?->warn("ManualAdmissions: section {$f['section']} introuvable — skip.");
                continue;
            }

            $matricule = 'CUK-2025-' . $f['key']; // ≤ 16 chars

            Candidat::query()->updateOrCreate(
                ['matricule_public' => $matricule],
                [
                    'concours_session_id'      => $session->id,
                    'centre_id'                => $centreId,
                    'nom'                      => $f['nom'],
                    'prenom'                   => $f['prenom'],
                    'date_naissance'           => '2000-01-01',
                    'lieu_naissance'           => 'Non renseigné',
                    'sexe'                     => $f['sexe'],
                    'nationalite_id'           => $natId,
                    'email'                    => 'pv2025.' . strtolower($f['key']) . '@cuk.local',
                    'telephone'                => 'PV-' . $f['key'],
                    'deja_bac'                 => true,
                    'annee_bac'                => 2025,
                    'serie_bac_id'             => $serieId,
                    'etablissement_frequente'  => 'Non renseigné',
                    'section_premier_choix_id' => $section->id,
                    'section_orientation_id'   => $section->id,
                    'statut'                   => Candidat::STATUS_ADMIS,
                    'admis_at'                 => now(),
                    'admission_note'           => "Admis ajouté manuellement depuis le procès-verbal 2025 — non inscrit en ligne "
                                                . "({$f['nom']} {$f['prenom']}). Identité à compléter.",
                ],
            );

            $this->command?->info("ManualAdmissions: {$f['nom']} {$f['prenom']} → admis {$f['section']} (ajouté, {$matricule}).");
        }
    }
}
