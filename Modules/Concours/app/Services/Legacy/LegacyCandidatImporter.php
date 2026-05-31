<?php

declare(strict_types=1);

namespace Modules\Concours\Services\Legacy;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;
use Modules\AcademicStructure\Models\Section;
use Modules\Concours\Models\Candidat;
use Modules\Concours\Models\Centre;
use Modules\Concours\Models\ConcoursSession;
use Modules\Referentiels\Models\Nationalite;
use Modules\Referentiels\Models\SerieBac;
use Modules\UserManagement\Models\User;
use Modules\UserManagement\Services\LegacyDumpParser;

/**
 * Imports the historical `etudiants` table → new `candidats`.
 *
 * Resolution map (legacy ID → new UUID), built before the loop:
 *   centres        : matched by lowercase nom
 *   sections       : matched by code (CI, AEC, IC, MEB, …)
 *   series_bac     : matched by code (C, D, SI, …)
 *   nationalites   : matched by exact French nom (case-folded)
 *
 * The `valid` column in legacy maps 1:1 to our STATUS_* constants.
 * Missing emails (very common in legacy data — registration form combined
 * email+tel into one field) are kept as NULL; the partial unique index
 * allows multiple nulls per session.
 *
 * Idempotent: a re-run skips any row whose `legacy_id` already exists.
 */
final class LegacyCandidatImporter
{
    /**
     * Status ranking — when the same person registered twice in a session,
     * the row with the most advanced status wins (admis > valid > oui > non
     * > rejete). Ties are broken by the highest legacy_id (latest entry).
     */
    private const STATUS_PRIORITY = [
        Candidat::STATUS_ADMIS  => 5,
        Candidat::STATUS_VALID  => 4,
        Candidat::STATUS_OUI    => 3,
        Candidat::STATUS_NON    => 2,
        Candidat::STATUS_REJETE => 1,
    ];

    public function import(
        LegacyDumpParser $parser,
        LegacyImportContext $context,
        LegacyImportReport $report,
        bool $dryRun,
    ): void {
        $this->buildReferentialMaps($parser, $context);

        $defaultSession = ConcoursSession::query()
            ->whereNotNull('legacy_id')
            ->orderByDesc('legacy_id')
            ->first();

        $etudiantRows = $parser->rowsOf('etudiants');

        // Snapshot every idetu present in the source dump so the dependent
        // importers can tell "candidat genuinely missing here" from
        // "orphan reference into a candidat already deleted upstream".
        foreach ($etudiantRows as $r) {
            $id = (int) ($r['idetu'] ?? 0);
            if ($id !== 0) { $context->legacyEtudiantIds[$id] = true; }
        }

        [$winners, $aliasOfWinner] = $this->dedupeEtudiants($etudiantRows);

        // Each dropped duplicate is reported once as "ignored" so the totals
        // still add up to the count of source etudiants rows.
        foreach ($aliasOfWinner as $aliasLegacyId => $_winner) {
            $report->skippedOne('candidats');
        }

        // Email uniqueness map, preloaded from already-imported rows so a
        // re-run sees prior emails and disambiguates consistently. Keyed by
        // "{sessionId}|{lowerEmail}" → legacy_id of the first owner. Two
        // genuinely-different people who share an email (proven distinct by the
        // dedupe above) each keep a deliverable address: the first keeps the
        // clean email, subsequent owners get a "+{legacyId}" plus-tag so the
        // (session, email) unique index holds without dropping anyone.
        $seenEmails = [];
        foreach (
            Candidat::query()->whereNotNull('legacy_id')
                ->get(['legacy_id', 'concours_session_id', 'email'])
            as $c
        ) {
            $em = mb_strtolower(trim((string) $c->email));
            if ($em !== '') {
                $seenEmails["{$c->concours_session_id}|{$em}"] ??= (int) $c->legacy_id;
            }
        }

        foreach ($winners as $row) {
            $legacyId = (int) ($row['idetu'] ?? 0);
            if ($legacyId === 0) {
                $report->skippedOne('candidats');
                continue;
            }

            try {
                $existing = Candidat::query()->where('legacy_id', $legacyId)->first();
                if ($existing !== null) {
                    $context->candidatByLegacyId[$legacyId] = (string) $existing->id;
                    $report->skippedOne('candidats');
                    continue;
                }

                $session = $this->resolveSession($context, (int) ($row['idan'] ?? 0)) ?? $defaultSession;
                if ($session === null) {
                    $report->failedOne('candidats', (string) $legacyId, 'Aucune ConcoursSession cible.');
                    continue;
                }

                $centreId  = $context->centreByLegacyId[(int) ($row['idcent'] ?? 0)] ?? null;
                $sectionId = $context->sectionByLegacyId[(int) ($row['idsect'] ?? 0)] ?? null;
                $bacId     = $context->serieBacByLegacyId[(int) ($row['idbac'] ?? 0)] ?? null;
                $natId     = $this->resolveNationalite($context, (string) ($row['nationalite'] ?? ''));

                if ($centreId === null || $sectionId === null || $bacId === null || $natId === null) {
                    $report->failedOne('candidats', (string) $legacyId,
                        sprintf('FK manquante (centre=%s, section=%s, bac=%s, nat=%s).',
                            $centreId ?? 'null', $sectionId ?? 'null', $bacId ?? 'null', $natId ?? 'null',
                        ));
                    continue;
                }

                // Old versions of the registration form (a) didn't require
                // either contact field and (b) frequently let users type their
                // email into the `tel` column (UI confusion — the form had
                // only one field labelled "contact"). Recover the email when
                // we see an '@' in the raw tel.
                $rawTelString   = trim((string) ($row['tel'] ?? ''));
                $rawEmail       = $this->normaliseEmail($row['email'] ?? null);
                if ($rawEmail === null && str_contains($rawTelString, '@')) {
                    $rawEmail     = $this->normaliseEmail($rawTelString);
                    $rawTelString = ''; // consumed by email
                }
                $rawTel = $this->normalisePhone($rawTelString);

                // Resolve a unique, deliverable email for this session. A null
                // email becomes the legacy-{id}@cuk.local placeholder; a real
                // email that's already taken in this session by a *different*
                // person gets a +{legacyId} plus-tag (routes to the same inbox,
                // keeps the (session,email) unique index intact). Phones, by
                // contrast, are allowed to repeat — families genuinely share a
                // number and "+1" wouldn't be a valid phone — that's handled by
                // the legacy-exempt telephone index, not here.
                $email = $this->resolveUniqueEmail($rawEmail, $legacyId, (string) $session->id, $seenEmails);

                $candidat = new Candidat([
                    'concours_session_id'      => $session->id,
                    'centre_id'                => $centreId,
                    'user_id'                  => $this->resolveUserId($row),
                    'nom'                      => mb_strtoupper(trim((string) ($row['nom'] ?? ''))),
                    'prenom'                   => $this->ucWords(trim((string) ($row['prenom'] ?? ''))),
                    'date_naissance'           => $row['dtnais'] ?? '1970-01-01',
                    'lieu_naissance'           => trim((string) ($row['lieunais'] ?? 'Inconnu')),
                    'sexe'                     => in_array($row['sexe'] ?? null, ['M', 'F'], true) ? $row['sexe'] : 'M',
                    'nationalite_id'           => $natId,
                    'email'                    => $email,
                    'telephone'                => $rawTel !== '' ? $rawTel : "LEGACY-{$legacyId}",
                    'deja_bac'                 => $row['dejabac'] !== null,
                    'annee_bac'                => $row['annebac'] !== null ? (int) $row['annebac'] : null,
                    'serie_bac_id'             => $bacId,
                    'bac_libelle_libre'        => $row['bac_name'] ?: null,
                    'etablissement_frequente'  => trim((string) ($row['eta_fre'] ?? 'Non précisé')),
                    'section_premier_choix_id' => $sectionId,
                    'section_second_choix_id'  => $context->sectionByLegacyId[(int) ($row['idsect2'] ?? 0)] ?? null,
                    'statut'                   => $this->mapStatut((string) ($row['valid'] ?? 'non')),
                    'matricule_public'         => $this->generateMatricule(),
                ]);
                $candidat->forceFill(['legacy_id' => $legacyId]);

                if (! $dryRun) {
                    $candidat->save();
                    $context->candidatByLegacyId[$legacyId] = (string) $candidat->id;
                    // Map every dropped duplicate's legacy_id to the same UUID
                    // so documents/motifs/payments attach to the winning row.
                    foreach ($aliasOfWinner as $aliasId => $winnerId) {
                        if ($winnerId === $legacyId) {
                            $context->candidatByLegacyId[$aliasId] = (string) $candidat->id;
                        }
                    }
                }
                $report->importedOne('candidats');
            } catch (UniqueConstraintViolationException) {
                // Should not happen after dedupeEtudiants, but kept as a safety
                // net so a fresh dupe in the source data never crashes the run.
                $report->skippedOne('candidats');
            } catch (\Throwable $e) {
                $report->failedOne('candidats', (string) $legacyId, $e->getMessage());
            }
        }
    }

    /**
     * Pre-pass de-duplication: for every (idan, telephone) and (idan, email)
     * pair, keep only the row whose status is the most advanced (admis →
     * valid → oui → non → rejete), tie-broken by the highest legacy_id.
     *
     * @param  list<array<string, string|null>>  $rows
     * @return array{0: list<array<string, string|null>>, 1: array<int, int>}
     *         [ winners, aliasLegacyId => winnerLegacyId ]
     */
    private function dedupeEtudiants(array $rows): array
    {
        /** @var array<int, array<string, string|null>> $winners keyed by idetu */
        $winners = [];
        /** @var array<string, int> $byKey composite identity key → winner idetu
         *  (see dedupeKeysFor: "{idan}|{contact}|name:…" / "…|dob:…") */
        $byKey = [];
        /** @var array<int, int> $alias  loserIdetu → winnerIdetu */
        $alias = [];

        foreach ($rows as $row) {
            $legacyId = (int) ($row['idetu'] ?? 0);
            if ($legacyId === 0) { continue; }

            $keys = $this->dedupeKeysFor($row);
            $collidingLegacyId = null;
            foreach ($keys as $k) {
                if (isset($byKey[$k])) { $collidingLegacyId = $byKey[$k]; break; }
            }

            if ($collidingLegacyId === null) {
                $winners[$legacyId] = $row;
                foreach ($keys as $k) { $byKey[$k] = $legacyId; }
                continue;
            }

            $existing = $winners[$collidingLegacyId];
            if ($this->compare($row, $existing) > 0) {
                // New row wins — replace, and re-key by the new row's keys.
                foreach ($this->dedupeKeysFor($existing) as $k) { unset($byKey[$k]); }
                $alias[$collidingLegacyId] = $legacyId;
                // Any prior aliases that pointed at the loser now point at the new winner.
                foreach ($alias as $a => $w) {
                    if ($w === $collidingLegacyId) { $alias[$a] = $legacyId; }
                }
                unset($winners[$collidingLegacyId]);
                $winners[$legacyId] = $row;
                foreach ($keys as $k) { $byKey[$k] = $legacyId; }
            } else {
                $alias[$legacyId] = $collidingLegacyId;
            }
        }

        return [array_values($winners), $alias];
    }

    /**
     * Identity-aware dedupe keys.
     *
     * A shared phone or email is NOT sufficient on its own to declare two
     * `etudiants` rows the same human — in the field, applicants routinely
     * share a contact (a parent's number, a sibling's email, a cyber-café
     * phone). The 2025 dump had at least 5 such collisions where two clearly
     * distinct candidates (different names AND different birthdates) were
     * merged away, including paid ones.
     *
     * So we require BOTH a shared contact AND an identity match. We express
     * the "name OR DOB" disjunction by emitting, per contact, two composite
     * keys:
     *
     *     {idan}|{contact}|name:{normalisedName}
     *     {idan}|{contact}|dob:{normalisedDob}
     *
     * Two rows collide iff they share at least one composite key, i.e. they
     * have the same contact AND (same name OR same DOB). This tolerates a typo
     * in either single field (a misspelled name still matches on DOB; a wrong
     * birthdate still matches on name) while keeping genuinely different people
     * apart. Scoping every key by `idan` keeps it correct across multiple
     * sessions in one dump — the same human re-applying in a later session is
     * legitimately a separate candidature.
     *
     * Complexity stays O(rows) — each row emits at most 4 keys.
     *
     * @param array<string, string|null> $row
     * @return list<string>
     */
    private function dedupeKeysFor(array $row): array
    {
        $idan         = (int) ($row['idan'] ?? 0);
        $rawTelString = trim((string) ($row['tel'] ?? ''));
        $email        = $this->normaliseEmail($row['email'] ?? null);
        // Mirror the insert-time routing: if tel actually holds an email, key
        // on the email side so two rows that put the same address in `tel`
        // dedupe correctly.
        if ($email === null && str_contains($rawTelString, '@')) {
            $email        = $this->normaliseEmail($rawTelString);
            $rawTelString = '';
        }
        $tel = $this->normalisePhone($rawTelString);

        $contacts = [];
        if ($tel !== '')     { $contacts[] = 'tel:' . $tel; }
        if ($email !== null) { $contacts[] = 'email:' . $email; }
        if ($contacts === []) {
            // No contact at all → nothing to collide on; the row is its own
            // identity (the caller treats an empty key list as "never merges").
            return [];
        }

        $name = $this->normaliseNameKey((string) ($row['nom'] ?? '') . ' ' . (string) ($row['prenom'] ?? ''));
        $dob  = $this->normaliseDobKey($row['dtnais'] ?? null);

        $keys = [];
        foreach ($contacts as $contact) {
            if ($name !== '')  { $keys[] = "{$idan}|{$contact}|name:{$name}"; }
            if ($dob !== null) { $keys[] = "{$idan}|{$contact}|dob:{$dob}"; }
            // Degenerate row: a contact but neither a usable name nor DOB. Fall
            // back to contact-only so a true repeat of such a sparse row still
            // collapses (we'd rather merge an anonymous repeat than split it).
            if ($name === '' && $dob === null) {
                $keys[] = "{$idan}|{$contact}";
            }
        }
        return $keys;
    }

    /**
     * Normalise a full name for identity matching: strip accents, drop every
     * non-letter, upper-case. "Mbégha-Koulibali Mouhamed " → "MBEGHAKOULIBALIMOUHAMED".
     * Resistant to spacing / punctuation / accent noise in the legacy data.
     */
    private function normaliseNameKey(string $raw): string
    {
        $ascii   = Str::ascii($raw);
        $letters = preg_replace('/[^A-Za-z]/', '', $ascii) ?? '';
        return mb_strtoupper($letters);
    }

    /**
     * Normalise a birthdate to Y-m-d, returning null for unusable / sentinel
     * values. The legacy form stored "I don't know" placeholders (1970-01-01,
     * 2000-01-01, 0000-00-00) that recur across unrelated candidates, so we
     * never key on them — otherwise two strangers sharing a phone AND a
     * placeholder date would merge. A null DOB just means this row relies on
     * the name key for identity.
     */
    private function normaliseDobKey(mixed $raw): ?string
    {
        $s = trim((string) ($raw ?? ''));
        if ($s === '') {
            return null;
        }
        try {
            $d = \Carbon\Carbon::parse($s)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
        $sentinels = ['1970-01-01', '2000-01-01', '0000-00-00'];
        return in_array($d, $sentinels, true) ? null : $d;
    }

    /**
     * @param array<string, string|null> $a
     * @param array<string, string|null> $b
     * @return int  >0 if a wins, <0 if b wins, 0 if equal.
     */
    private function compare(array $a, array $b): int
    {
        $pa = self::STATUS_PRIORITY[$this->mapStatut((string) ($a['valid'] ?? 'non'))] ?? 0;
        $pb = self::STATUS_PRIORITY[$this->mapStatut((string) ($b['valid'] ?? 'non'))] ?? 0;
        if ($pa !== $pb) { return $pa <=> $pb; }
        return ((int) ($a['idetu'] ?? 0)) <=> ((int) ($b['idetu'] ?? 0));
    }

    private function buildReferentialMaps(LegacyDumpParser $parser, LegacyImportContext $context): void
    {
        // centres: legacy nom → new id (we matched by name during seeding,
        // so we re-match the same way here for consistency).
        $newCentresByLowerNom = Centre::query()->get(['id', 'nom'])
            ->keyBy(fn ($c) => mb_strtolower(trim($c->nom)));

        foreach ($parser->rowsOf('centres') as $row) {
            $key = mb_strtolower(trim((string) ($row['nom'] ?? '')));
            if ($key !== '' && $newCentresByLowerNom->has($key)) {
                $context->centreByLegacyId[(int) ($row['idcent'] ?? 0)] = (string) $newCentresByLowerNom[$key]->id;
            }
        }

        // Case-insensitive code match — legacy data has e.g. "Autre" while our
        // seeder normalised it to "AUTRE"; the new schema doesn't guarantee
        // a particular case.
        $newSectionsByCode = Section::query()->get(['id', 'code'])
            ->keyBy(fn ($s) => mb_strtolower(trim((string) $s->code)));
        foreach ($parser->rowsOf('sections') as $row) {
            $code = mb_strtolower(trim((string) ($row['code'] ?? '')));
            if ($code !== '' && $newSectionsByCode->has($code)) {
                $context->sectionByLegacyId[(int) ($row['idsect'] ?? 0)] = (string) $newSectionsByCode[$code]->id;
            }
        }

        $newBacByCode = SerieBac::query()->get(['id', 'code'])
            ->keyBy(fn ($b) => mb_strtolower(trim((string) $b->code)));
        foreach ($parser->rowsOf('series_bac') as $row) {
            $code = mb_strtolower(trim((string) ($row['code'] ?? '')));
            if ($code !== '' && $newBacByCode->has($code)) {
                $context->serieBacByLegacyId[(int) ($row['idbac'] ?? 0)] = (string) $newBacByCode[$code]->id;
            }
        }

        // nationalites: legacy stored a string "Gabonais" in candidat rows
        // (not an FK), so map by lowercased nom.
        $context->nationaliteByLegacyName = Nationalite::query()->get(['id', 'nom'])
            ->mapWithKeys(fn ($n) => [mb_strtolower(trim($n->nom)) => (string) $n->id])
            ->all();
    }

    private function resolveSession(LegacyImportContext $context, int $idan): ?ConcoursSession
    {
        // The candidat row points to idan, not idconc. We match the session
        // whose legacy_id maps back via the LegacyConcoursImporter.
        foreach ($context->sessionByLegacyId as $sessionUuid) {
            $s = ConcoursSession::query()->find($sessionUuid);
            if ($s !== null && (int) $s->legacy_id === $idan) {
                return $s;
            }
        }
        return ConcoursSession::query()->whereNotNull('legacy_id')->orderByDesc('legacy_id')->first();
    }

    private function resolveNationalite(LegacyImportContext $context, string $rawName): ?string
    {
        $key = mb_strtolower(trim($rawName));
        if ($key === '') {
            return $context->nationaliteByLegacyName['gabonais'] ?? null;
        }
        return $context->nationaliteByLegacyName[$key]
            ?? $context->nationaliteByLegacyName['gabonais']
            ?? null;
    }

    private function resolveUserId(array $row): ?string
    {
        // Legacy candidats never had user accounts. The only way they get
        // one is via Stage 5B SelectionService — so on import, leave null.
        return null;
    }

    private function mapStatut(string $legacy): string
    {
        return match ($legacy) {
            'oui'    => Candidat::STATUS_OUI,
            'valid'  => Candidat::STATUS_VALID,
            'rejete' => Candidat::STATUS_REJETE,
            'admis'  => Candidat::STATUS_ADMIS,
            default  => Candidat::STATUS_NON,
        };
    }

    /**
     * Pick a unique, deliverable email for a legacy candidat within a session.
     *
     *   - No real email           → "legacy-{legacyId}@cuk.local" (unique by id).
     *   - Real email, first owner → kept as-is.
     *   - Real email, already used by a DIFFERENT person in this session →
     *     plus-tagged "local+{legacyId}@domain" (e.g. two siblings who reused
     *     one Gmail). Plus-addressing routes to the same inbox, so the address
     *     stays real while satisfying the (session, lower(email)) unique index.
     *
     * `$seen` is mutated to record the chosen address so the next winner in the
     * same run collides against it too. Deterministic + idempotent: the same
     * row always resolves to the same address on a re-run because the tag is
     * the stable legacy id, not a positional counter.
     *
     * @param array<string,int> $seen  "{sessionId}|{lowerEmail}" => owner legacy_id
     */
    private function resolveUniqueEmail(?string $rawEmail, int $legacyId, string $sessionId, array &$seen): string
    {
        if ($rawEmail === null || $rawEmail === '') {
            return "legacy-{$legacyId}@cuk.local";
        }

        $email = mb_strtolower(trim($rawEmail));
        $key   = "{$sessionId}|{$email}";

        // Free, or already attributed to THIS same row (re-run) → keep clean.
        if (! isset($seen[$key]) || $seen[$key] === $legacyId) {
            $seen[$key] = $legacyId;
            return $email;
        }

        // Taken by someone else → plus-tag with the legacy id before the '@'.
        $at = mb_strpos($email, '@');
        $tagged = $at === false
            ? "{$email}+{$legacyId}"
            : mb_substr($email, 0, $at) . "+{$legacyId}" . mb_substr($email, $at);

        $seen["{$sessionId}|{$tagged}"] = $legacyId;
        return $tagged;
    }

    private function normaliseEmail(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }
        $trimmed = trim($raw);
        if ($trimmed === '' || ! str_contains($trimmed, '@')) {
            return null;
        }
        return mb_strtolower($trimmed);
    }

    private function normalisePhone(string $raw): string
    {
        $stripped = preg_replace('/\s+/', '', trim($raw)) ?? $raw;
        return mb_substr($stripped, 0, 30);
    }

    private function ucWords(string $value): string
    {
        return mb_convert_case($value, MB_CASE_TITLE, 'UTF-8');
    }

    private function generateMatricule(): string
    {
        do {
            $m = 'CUK-' . Str::upper(Str::random(12));
        } while (Candidat::query()->where('matricule_public', $m)->exists());
        return $m;
    }
}
