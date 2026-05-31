<?php

declare(strict_types=1);

namespace Modules\Concours\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Modules\AcademicStructure\Models\Section;
use Modules\Concours\Models\Candidat;
use Modules\Concours\Models\ConcoursSession;

/**
 * Marks the candidats named on the 2025 procès-verbal (res_CUK_2025.pdf) as
 * `admis`, with their orientation = the section they were admitted into, so a
 * candidat consulting their dossier sees that they passed and the back-office
 * counts them as admitted.
 *
 * The PV is a SCANNED PDF (no text layer), so the admitted list below is a
 * manual transcription keyed by section code. Each name is fuzzy-matched to an
 * imported legacy candidat:
 *
 *   1. The candidate pool is SCOPED to applicants of that section (premier OR
 *      second choix) — by far the strongest guard against false positives.
 *   2. Names are normalised (accents stripped, OCR `1<`/`I<`→K, letters only)
 *      and compared with a Levenshtein similarity ratio.
 *   3. Pool match needs ≥ 0.86; a global fallback (any session candidat) needs
 *      ≥ 0.93. A candidat already matched is never reused.
 *
 * SAFE BY DEFAULT: runs as a DRY-RUN (reports matches + unmatched, writes
 * nothing). Set CONCOURS_ADMIS_APPLY=1 to actually persist:
 *
 *   php artisan db:seed --class="Modules\\Concours\\Database\\Seeders\\LegacyAdmissionResultsSeeder"            # dry run
 *   CONCOURS_ADMIS_APPLY=1 php artisan db:seed --class="Modules\\Concours\\Database\\Seeders\\LegacyAdmissionResultsSeeder"  # apply
 *
 * Idempotent: re-running re-matches the same rows. Unmatched names are listed
 * for manual resolution (admin candidat edit).
 */
final class LegacyAdmissionResultsSeeder extends Seeder
{
    // Token-containment thresholds (PV name ⊆ DB name; the PV truncates
    // middle names so the DB row has MORE tokens, not fewer).
    private const POOL_THRESHOLD   = 0.75;  // within the section's applicants
    private const GLOBAL_THRESHOLD = 0.90;  // any session candidat (stricter)
    private const TOKEN_SIM        = 0.72;  // two tokens "equal" if ≥ this (tolerates 1-char OCR)

    /**
     * Admitted candidates, by section CODE (DB codes; PV "GTER" = "GTE").
     * Transcribed from res_CUK_2025.pdf — best-effort OCR cleanup.
     *
     * @var array<string, list<string>>
     */
    private const ADMIS = [
        // TITRE 1 — A. Analyses Biologiques et Biochimiques (Article 1, 32)
        'ABB' => [
            'AKANDA-ADIBET BODIPO David Ernest',
            'ASSOUMOU NDONG Jarhed Joskan',
            "BABEY'DICKAT GALLY Tobya Orchidée",
            'BATSIANDJI DOUDOU Milland Irène',
            'DITOU DI KASS Kevine',
            'EKOME EKONIE Andy-Brian',
            'ENGOZOGO Ruth Vanelle Roisia',
            'IKOGHOTO BAPOLA Crépin',
            'KIALLO LONDO Chlorantha',
            'MABALA LEBAMA Sem Sylvain',
            'Anne Esther',
            'MASSALA BISSELO Joris Marlin',
            'MBENGA MINZELI Jehovanie Gracia',
            'MBOULA NGUIMITOUMA Jessipher Dorcelin',
            'MBOUMBA ONDO Jecolia Bérénice',
            'MBWA NTOTOME Hugue Mewin',
            'MENGUE NGUEMA IFUNANYA Miracle',
            'MIBANBO DIALLO Jancelia',
            'MOUNDZIBOUE NDENDZI Anould',
            'MOUTSINGA MABOKO Josephine Navida',
            "NGO'O ZEH Buvan Kurtys",
            'NGOUNDOU MOUELE Lucie Purieyne',
            'NZAMBA BIZA Giselle',
            'NZENGUE TOGHO Dafnin Chancia',
            'OBIANG OBAME Mayer',
            'OKANDZA MANDZA Joan',
            'OKOUMA Michelle Jolina',
            'OKOURI-MIERE Dan Rodney',
            'OSSOLO OUSSOU Lynne Sephora',
            'OVONO ONDO Julien',
            'YAZANGOYE YAZANGOYE Dorkas',
            'YOUBOU NDONGO Thietyl Darxel',
        ],
        // TITRE 1 — B. Maintenance des Équipements Biomédicaux (Article 2, 32)
        'MEB' => [
            'ADA DUTOURD Frayse',
            'BIKABI MARITA Jumaelle Benhi-Meriba',
            'BOUCKA BOUCKA Junior',
            'DIVOUNGOU NDONG Eddy Junior',
            'EDZO NGUEMA Euréka',
            'ELIE NZE Patrice Dewich',
            'GNOMBA-ONI FIFONSI Gracia Ornelle',
            'KOUMBA MOUANZA Yann',
            'KOUNDHA Sarah',
            'LESSA Morel Jarry',
            'MANGARI KOFFI Tshièsse Elie',
            'MBADINGA Francia Amicia',
            'MBADINGA MOUELI Marius Ryan',
            "MBAMANGHA-KASSA Steven's Asiange",
            'MBEAURYGHUY MBORETTA Elicha',
            'NGOUMBA SYMAT Dan Elvis',
            'MEKUI',
            'NENGUE OBAME OLLOMO Hercylia Maguie',
            'NEVOUNG-NZE-NANG Henri-Alex',
            'MODANGA BOPENGA Eudine Dominique',
            'MOUANDJA BOGO Vencel Ivaneo',
            'MOUBOUYI TOMBO Axel',
            'MOUNDOUMBELA NZIENGUI Mourel',
            'MUNDUNGA MUNDUNGA Tiburce-René',
            'NDOUTOUIME OLLOMO Herly Charly',
            'NGUEMA ONDO TOUNG Chandy Trésor',
            'NTAWANGA ELIKYA Josué-Royal',
            'NTSAME NZOGHE Ojyu-germaine',
            'NTSAME ZUE Conciliia Cerena',
            'POUNDI MANGANDA Grâce Lyse Morgan',
            'SALOMON IVAN LARYS LEMENGUET',
            'TCHILONGOU LAKISSI Asclepios Orphée',
        ],
        // TITRE 2 — A. Architecture et Éco-construction (Article 3, 36)
        'AEC' => [
            'ABENADJOLA NDJEMA Andy Joriack',
            'ABOGHE ZOGHO Lary Wilfried',
            'ADJONGBOUET RAGANIZO Sarah Ludwige',
            'AKOUE AKOUE Noël Mathias',
            'ALLOGHO Paul-Teddy',
            'ANGOUE BEKALE Keveen Junior',
            'BIYOGHE MENIE Kevine',
            'BOUPENDZA Aleron Wesley',
            'BOUSSAMBA Belorgez Meschac',
            'BOUSSOUGOU PACKA Firlin Espoir',
            'ELLA NDO Alery',
            'IVORA NZAHOU Naomi Triphene',
            'KASSA KASSA',
            'KOMBA MOUKAMBI Mayevha Marleine',
            'MACXON Elysée Vivien',
            'MAKOUYA MOUBAMBA Kenssy Darril',
            'MAMADOU Saley',
            'MAMBANAH MAMBANA Krys Orsen',
            'MBANGA Jemima',
            'MABOUNDOU',
            'MBOUMBA OSSIBEGA Dieu-Vie Flichnelle',
            'MOHAMED Moustapha',
            'MONDO MAGANGA Wilma Monica',
            'MOUDOUMA Warriss',
            'MOUNZIEGOU NGUEMBI Pierre Medgard',
            'MOUPAKA MINKANG Archange Waynne',
            'MOUSSAVOU Dijess Lanny',
            'MOUYOPI MOUITY Lynn Caresse',
            'MVE ALLOGO Darrel',
            'NDONG EDZANG Héritier Dorione',
            'NGONGA BIBANDA Glody Brown',
            'NGUEMA Orei Steph',
            'NGUERE Jerman',
            'NZAMBA NZAMBA Neels Élysée',
            'NZE NKOGHE Wilfried',
            'TSEMBOUA META Wylls Christ',
        ],
        // TITRE 2 — B. Chimie Industrielle (Article 4, 36)
        'CI' => [
            'BIWOMBO Providence',
            'BOUANGA MASMI Richman Emmanuel',
            'EYA EYELE Gaudei',
            'EYABILA KANIBANGOYE Christar Jean Steeve',
            'GANTOU AYVERA Leïla Delatoussaint',
            'KASSA-DINZAMBOU Auguste',
            'KOUMBA-BOUKA Stella-Melina',
            'LEPENGUE DENGUE Djamel',
            'LODI-MBIGHOU Claine',
            'LOUNDOU Edifa Braël',
            'MAGNOGNO NZEMBIT Petit-cheri',
            'MAKOUKOU NGUELE Khari Ulyss',
            'MANGOUMBA Dorcas Brévettie',
            'MAPINGOU Griselda-Sabruna',
            'MATADI Wen Junior',
            'MBEN Premier Christian',
            'MBOU Seth',
            'MBOUROU BOULINGUI Uldesse Darcy',
            'MEMVEME EKANG Joël-Duval',
            'MENZOGHE BITEGHE Quenryl Peursy',
            'MFOUGOU ELLA Valdy Derick',
            'MOUNDOUNGA-SANA Emmanuel Silas',
            'MOUPACKA LEKOGHO Laurent Yannick',
            'MOUSSAVOU MAILA Aubin',
            'MOUTOUPA NDJOKA MBONO Gérémi',
            'MFONO BOGOWE Grâce Esther',
            'NGNOMBA-MOLO MOUDIANGO',
            'NGOMA LEKOUMA Warren Steeven',
            'NGOUNDOU Dora Léïla Kélia',
            'NGUENGUILA MPOUYE Daniel',
            'NZUE EZEIGNE Daniel Victoire Salomon',
            'OBAME OBIANG Ghislain Régis',
            'OKONGO LOGUITINA Maïda Leslie',
            'SAFOU SAFOU Hans',
            'TSIMBA MOUSSAVOU Samyra Marianne',
            'WYLLIAM-AGAYA Charlie Glodie Esperence',
        ],
        // TITRE 2 — C. Génie Thermique et Énergies Renouvelables (Article 5, 32) [PV "GTER"]
        'GTE' => [
            'AKOUGA SOUMANKI Dardonie Shabanie',
            'BAKONOU Melisa Trifène',
            'BENGA ANGOUÉ Marie Jennifer',
            'BIBALOU MBOUMBA Edrifa Victoire',
            'BIVINGOU BOUKA BHY Nelya Eve',
            'BODI NZENGUE Juste Séraphin',
            'BOUNZANGA Jospin',
            'DENGUE NWALELE Bryann Vick',
            'IBINGA KOUMBA Grace Landrina',
            'IDJONGA Ketsia',
            'IGNOUMBA-IGNOUMBA Aimé-Brandy',
            'KOMBILA Maxe Jovanie',
            'KONDI MAYASS Bachir Amif',
            'KOUMBA Jean-Paul',
            'KOUSSOU BOULANGA Christelia Fenola',
            'MADJINOU KOUMBA Ursule Djynssy',
            'MADOUTH-MA-MAROGH Dildjeiel',
            'MLANGA MABIKA Marisca',
            'MBAKI BIBAYA Thinaut Christopher',
            'MELENG ZOLLO Graça-Pheldyne',
            'MENZUI OBAME Lyla',
            'MOUNGOUNDOU KENGUE Risdane Rita',
            'NDOYE MAGNANA Orléon',
            'NLEP SECOND Marc-Antoine',
            'NTOUNTOUME BIVIGOU Jordan Trecy',
            'NTOUTOUME ESSABA Bruno Christopher',
            'NZE DAOUDA Bilai',
            'OBIANG OBIANG Yvana Marie Emmanuelle',
            'OBONO ONDO Thérèsa Coralie',
            'OKOUE ABAGA Hans Ariel',
            'THOMYS-BIVIKA Hiram',
            'YEDI AMBOUROUET Luce Kerlia Joëlle',
        ],
        // TITRE 2 — D. Informatique et Communications (Article 6, 32)
        'IC' => [
            'AKOAKOU Dona Rébecca',
            "ANDEM'AKOUE Graslya Luckariv Ineijda",
            'AYENOUET Patrick Guyliane',
            'BABIKA Robertha Précilia',
            'BITENDA KOPYONGHO Harmonie Rayann',
            'BOKOPE NGOMBI-BADI Géraud Chantilly',
            'BONGO OBIAJULU Harvé Gift',
            'CHINYCOTITA NWOKEMA Emmanuel',
            'DIBADI DIBADI Excel Felicien',
            'EBANG MBELE Axel Schavais',
            'ETOUGHE MICKO Dezie Ruddel',
            'ETOUMBI KONDO Ruben',
            'EYOUCKA MOUTINGA Osmi Syndy',
            'IBANGA-BOMBA Jurmélia-Lapelle',
            'LENDJANDJA LEYAMANGOYE Glonel',
            'LOUMBA Franck Blanchard',
            'LOUNDOU MATOUMBA Guisty-Darley',
            'MABARY MADOUNI MPOLO Suzy Hardy',
            'MAGNOUNGOU TANTAL Eveil Claudel',
            'MAMBILY ANGOUETSE Alan Sleed',
            'MBANDA BILEMBI Seguy Rhiver',
            'MOKAGNA DELEME Dylane Cœur-tyss',
            'NDONG NZE Jean De La France',
            'NDZENG EYEGHE Darryl',
            'NGAKORI Davi',
            'NGOUANDA NGOUANDA Wayne Audrey',
            'NZAMBA BOUSSAMBA Léonce Kelly',
            'OFOUNDA MEZUI Jordan Celestin',
            'ONDO MBA Emmanuel',
            'ONTOUNGA NGUEDI Nadine',
            'ONTSAGA OTALA Jared',
            'SEPH POATY Joseph Capps Branham',
        ],
        // TITRE 2 — E. Productique Mécanique (Article 7, 32)
        'PM' => [
            'ABOUT NKOGHE Alice',
            'AKOMA NGUEMA OBINWANNE Morine',
            'ASSIMBAULT MBAMBALT Jeremy',
            'BIOGHA MAKONGO Winie Cassandra',
            'BOULANGA NKOMBO Ivan Christopher',
            'DOUSSE SIMANGOYE Velly Olsere',
            'IBINGA NDOBA Israël',
            'KONONGO NGANDOU MOUKANDA Guynancia',
            'KOUANDJI Aimé Franck',
            'KOUMBA KOUMBA Dernely',
            'LEBOUTSOU Ezequiél',
            'MASSOUNA MALEMA Maryse Anicia',
            'MBOUROU FOCK Anges Romain',
            'MBOYI Claude',
            'MENZUE Laure Merveille',
            'MOMBO-LETAMBA Ayek-Oliver',
            'MOUELE YANANGA Chavely',
            'MOUNZIEGOU MBADINGA Sysiam Josué',
            'NDAKISSA Brenda Anastasia',
            'NDOUHO Reslia Keïsha',
            'NGARI NGUIMI Mouhamed Yacine',
            'NGOUBOU Christ',
            'NTINTIRI Leyla Bettyna Machlie',
            'NZAMBA NZAMBA Djess Harsy',
            'ONA IBE',
            'ONWANLELE OZENDO Glenn Paul Maximilien',
            'PANGOU BOUNZANGA Orly Cyr',
            'REZALAGUA OGOULA Patrice',
            'TCHOKECHA TSAGHA Rihanna Maliçia',
            'YAYILAT Monica Celeste',
            'YOUBOU-LENDOYE Wilde-Vaney',
        ],
    ];

    public function run(): void
    {
        $apply = (string) env('CONCOURS_ADMIS_APPLY', '0') === '1';

        $session = ConcoursSession::query()->where('code', 'CONCOURS-LEGACY-2025')->first()
            ?? ConcoursSession::active();
        if ($session === null) {
            $this->command?->warn('Admission: aucune session cible — skip.');

            return;
        }

        /** @var array<string, Section> $sectionsByCode */
        $sectionsByCode = Section::query()->get()->keyBy('code')->all();

        // Preload every candidat of the session once, with a normalised name.
        $all = Candidat::query()
            ->where('concours_session_id', $session->id)
            ->get(['id', 'nom', 'prenom', 'statut', 'section_premier_choix_id', 'section_second_choix_id', 'section_orientation_id']);

        // Precompute, per candidat: token list + normalised full string.
        $tokens   = [];
        $normName = [];
        foreach ($all as $c) {
            $full = $c->nom . ' ' . $c->prenom;
            $tokens[$c->id]   = $this->tokens($full);
            $normName[$c->id] = $this->norm($full);
        }

        $matchedIds  = [];   // candidat_id => true
        $assignments = [];   // candidat_id => section_id
        $report      = [];   // section_code => [matched, total, review[], missing[]]

        foreach (self::ADMIS as $code => $names) {
            $section = $sectionsByCode[$code] ?? null;
            if ($section === null) {
                $this->command?->warn("Admission: section {$code} introuvable — skip.");
                continue;
            }

            $pool = $all->filter(fn (Candidat $c): bool =>
                $c->section_premier_choix_id === $section->id
                || $c->section_second_choix_id === $section->id);

            $matched = 0;
            $review  = [];   // accepted but non-exact / cross-section → eyeball
            $missing = [];

            foreach ($names as $rawName) {
                $pv = $this->tokens($rawName);
                if ($pv === []) {
                    $missing[] = "{$rawName}  (illisible)";
                    continue;
                }

                // 1) section pool (token-containment)
                [$id, $score] = $this->bestMatch($pv, $pool, $tokens, $normName, $matchedIds);
                $scope = 'pool';

                // 2) global fallback — stricter, never on a single token.
                if ($id === null || $score < self::POOL_THRESHOLD) {
                    [$gid, $gscore] = $this->bestMatch($pv, $all, $tokens, $normName, $matchedIds);
                    if (count($pv) >= 2 && $gid !== null && $gscore >= self::GLOBAL_THRESHOLD) {
                        $id = $gid; $score = $gscore; $scope = 'global';
                    } else {
                        // miss — diagnostic shows the closest we could see
                        $closest = $gid !== null ? $all->firstWhere('id', $gid) : null;
                        $missing[] = sprintf('%s  →  proche: %s (%.2f)',
                            $rawName,
                            $closest ? ($closest->nom . ' ' . $closest->prenom) : '—',
                            $gscore);
                        continue;
                    }
                }

                $matchedIds[$id]  = true;
                $assignments[$id] = $section->id;
                $matched++;

                $cand = $all->firstWhere('id', $id);
                $candName = $cand ? ($cand->nom . ' ' . $cand->prenom) : '?';
                if ($scope === 'global' || $score < 1.0) {
                    $review[] = sprintf('%s  →  %s  [%s %.2f]', $rawName, $candName, $scope, $score);
                }
            }

            $report[$code] = [
                'matched' => $matched,
                'total'   => count($names),
                'review'  => $review,
                'missing' => $missing,
            ];
        }

        // ---- Apply (or report only) ----
        if ($apply) {
            foreach ($assignments as $candidatId => $sectionId) {
                Candidat::query()->where('id', $candidatId)->update([
                    'statut'                 => Candidat::STATUS_ADMIS,
                    'section_orientation_id' => $sectionId,
                    'admis_at'               => now(),
                ]);
            }
        }

        // ---- Report ----
        $totalMatched = count($assignments);
        $totalNames   = array_sum(array_map(fn (array $r): int => $r['total'], $report));

        $this->command?->info(($apply ? 'APPLIED' : 'DRY-RUN (nothing written)')
            . " — admis matchés: {$totalMatched}/{$totalNames}");

        foreach ($report as $code => $r) {
            $this->command?->line(sprintf('  %-4s %2d/%2d', $code, $r['matched'], $r['total']));
            foreach ($r['review'] as $line) {
                $this->command?->line("        à vérifier: {$line}");
            }
            foreach ($r['missing'] as $line) {
                $this->command?->warn("        NON TROUVÉ: {$line}");
            }
        }

        if (! $apply) {
            $this->command?->comment('Relancez avec CONCOURS_ADMIS_APPLY=1 pour écrire les statuts admis.');
        }
    }

    /**
     * Best candidat in $pool for the admitted-name tokens, by token-containment
     * (PV name ⊆ DB name — the PV truncates middle names). Skips already-matched
     * rows; ties broken by full-string similarity (prefers the most exact, i.e.
     * fewest extra DB tokens). Returns [candidat_id|null, score].
     *
     * @param  list<string>  $pvTokens
     * @param  \Illuminate\Support\Collection<int, Candidat>  $pool
     * @param  array<string, list<string>>  $tokens
     * @param  array<string, string>        $normName
     * @param  array<string, bool>          $matchedIds
     * @return array{0: string|null, 1: float}
     */
    private function bestMatch(array $pvTokens, $pool, array $tokens, array $normName, array $matchedIds): array
    {
        $bestId    = null;
        $bestScore = 0.0;
        $bestTie   = -1.0;
        $pvNorm    = implode('', $pvTokens);

        foreach ($pool as $c) {
            if (isset($matchedIds[$c->id])) {
                continue;
            }
            $score = $this->containment($pvTokens, $tokens[$c->id] ?? []);
            if ($score < 0.5) {
                continue;
            }
            $tie = $this->sim($pvNorm, $normName[$c->id] ?? '');
            if ($score > $bestScore || ($score === $bestScore && $tie > $bestTie)) {
                $bestScore = $score;
                $bestTie   = $tie;
                $bestId    = $c->id;
            }
        }

        return [$bestId, $bestScore];
    }

    /**
     * Fraction of PV tokens present (fuzzily) among the DB tokens. 1.0 ⇒ every
     * admitted-name token appears in the candidat's (longer) full name.
     *
     * @param  list<string>  $pv
     * @param  list<string>  $db
     */
    private function containment(array $pv, array $db): float
    {
        if ($pv === []) {
            return 0.0;
        }
        $hits = 0;
        foreach ($pv as $pt) {
            foreach ($db as $dt) {
                if ($pt === $dt || $this->sim($pt, $dt) >= self::TOKEN_SIM) {
                    $hits++;
                    break;
                }
            }
        }

        return $hits / count($pv);
    }

    /**
     * Normalised name tokens: accent-stripped, OCR-fixed (`1<`/`I<`→K),
     * uppercase, split on non-letters, 1-char noise dropped.
     *
     * @return list<string>
     */
    private function tokens(string $s): array
    {
        $s = Str::ascii($s);
        $s = mb_strtoupper($s);
        $s = str_replace(['1<', 'I<', 'L<', '|<', '<'], 'K', $s);
        $parts = preg_split('/[^A-Z]+/', $s, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_filter($parts, fn (string $t): bool => strlen($t) >= 2));
    }

    /** Accent-stripped, OCR-fixed, letters-only uppercase key (tokens joined). */
    private function norm(string $s): string
    {
        return implode('', $this->tokens($s));
    }

    /** Levenshtein-based similarity ratio in [0,1]. */
    private function sim(string $a, string $b): float
    {
        if ($a === '' || $b === '') {
            return 0.0;
        }
        if ($a === $b) {
            return 1.0;
        }
        $max = max(strlen($a), strlen($b));
        // levenshtein() caps at 255 bytes; names are far shorter.
        $dist = levenshtein(substr($a, 0, 255), substr($b, 0, 255));

        return 1.0 - ($dist / $max);
    }
}
