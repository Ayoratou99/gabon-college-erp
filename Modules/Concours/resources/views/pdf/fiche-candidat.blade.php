{{-- Fiche d'inscription au concours — reproduction fidèle de la mise en page
     historique (php/fiche_etudiant.php legacy CUPK), compressée pour tenir
     sur une seule page A4. Le logo CUK provient de Parametrage
     (site.brand.logo_url) pour pouvoir être mis à jour sans redéploiement. --}}
@php
    use Modules\Parametrage\Services\SettingsService;

    // ---- Helpers : embed every image as a data URI ----
    // DomPDF tourne hors HTTP : on ne peut pas se reposer sur des URLs
    // accessibles (le binaire ne fait pas de DNS, et même les chemins
    // /storage/... ne sont pas atteignables hors webserver). On lit donc
    // les bytes en local et on injecte du data:image/...
    $imgFromPublic = static function (string $rel): ?string {
        $abs = public_path(ltrim($rel, '/'));
        if (! is_file($abs)) { return null; }
        $bytes = @file_get_contents($abs);
        if ($bytes === false) { return null; }
        $ext = strtolower(pathinfo($abs, PATHINFO_EXTENSION));
        $mime = match ($ext) {
            'png'  => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            default => 'application/octet-stream',
        };
        return 'data:' . $mime . ';base64,' . base64_encode($bytes);
    };

    // Logo CUK : on lit la valeur Parametrage en priorité (paramètre
    // site.brand.logo_url, modifiable depuis /admin/parametrage), avec
    // fallback sur le logo embarqué dans public/img/cuk/.
    $logoUrl = (string) app(SettingsService::class)->get('site.brand.logo_url', '/img/cuk/logo.jpg');
    $logoImg = $imgFromPublic($logoUrl) ?? $imgFromPublic('img/cuk/logo.jpg');

    $ustmImg  = $imgFromPublic('img/cuk/ustm.png');
    $sceauImg = $imgFromPublic('img/cuk/sceau.jpg');

    // ---- Choix de formation : ids pour cocher les carrés ----
    $idSect1 = $candidat->section_premier_choix_id;
    $idSect2 = $candidat->section_second_choix_id;
    $hasSecondChoice = $idSect2 !== null && $idSect2 !== '' && (string) $idSect2 !== (string) $idSect1;

    // ---- BAC ----
    $serieCode  = $candidat->serieBac?->code;
    $bacLibelle = (mb_strtolower((string) $serieCode) === 'autre' || $serieCode === null)
        ? ($candidat->bac_libelle_libre ?: ($candidat->serieBac?->nom ?? '—'))
        : ($candidat->serieBac?->nom ?? $serieCode);

    // ---- Statut lisible ----
    $statutLabel = match ($candidat->statut) {
        'valid'  => 'PAYÉ',
        'oui'    => 'ACCEPTÉ (à payer)',
        'non'    => 'EN COURS',
        'rejete' => 'REJETÉ',
        'admis'  => 'ADMIS',
        default  => strtoupper((string) $candidat->statut),
    };
@endphp
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Fiche d'inscription — {{ $candidat->matricule_public }}</title>
    <style>
        /* Marges minimales pour tenir sur 1 page A4. */
        @page { margin: 0.9cm 0.9cm; }
        body  { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 9pt; color: #000; line-height: 1.25; }
        .underline { text-decoration: underline; }
        .small     { font-size: 7.5pt; color: #555; }

        /* --- En-tête à trois colonnes --- */
        table.header { width: 100%; border-collapse: collapse; }
        table.header td {
            border: 1px solid gray; vertical-align: middle; padding: 5pt;
        }
        td.ministere { width: 44%; font-size: 7.5pt; line-height: 1.25; }
        td.photo     { width: 26%; text-align: center; height: 115pt; }
        td.sceau     { width: 30%; text-align: center; }
        td.photo .photo-frame {
            width: 85pt; height: 100pt; margin: 0 auto;
            border: 1px solid #ccc; background: #f0f0f0; text-align: center;
        }
        td.photo .photo-frame img { max-width: 80pt; max-height: 95pt; margin-top: 2pt; }
        td.photo .photo-missing { color: #888; font-size: 7pt; padding-top: 42pt; }

        /* --- Titre --- */
        .formulaire {
            text-align: center; margin: 8pt 0 6pt;
        }
        .formulaire h3 { color: gray; font-size: 12pt; margin: 0; font-weight: bold; }
        .matricule-band {
            margin-top: 3pt; font-family: Courier, monospace; font-size: 9pt;
            background: #f1f5f9; border: 1px solid gray;
            padding: 2pt 6pt; display: inline-block;
        }

        /* --- ÉTAT CIVIL : grille deux colonnes pour économiser la place --- */
        .etat-civil {
            border: 1px solid gray; padding: 6pt 8pt; margin-top: 6pt;
        }
        .etat-civil .ec-title {
            font-weight: bold; font-size: 9.5pt; margin-bottom: 4pt;
        }
        table.kv-grid { width: 100%; border-collapse: collapse; }
        table.kv-grid td { padding: 2pt 6pt 2pt 0; vertical-align: top; font-size: 9pt; }
        table.kv-grid td.lbl { width: 18%; font-weight: bold; white-space: nowrap; }
        table.kv-grid td.val { width: 32%; }
        table.kv-grid td.val .underline { display: inline-block; min-width: 80pt; }

        /* --- Tables génériques --- */
        table.boxed { width: 100%; border-collapse: collapse; margin-top: 6pt; }
        table.boxed th, table.boxed td {
            border: 1px solid gray; padding: 4pt 6pt; vertical-align: middle;
            font-size: 9pt;
        }
        table.boxed caption {
            caption-side: top; font-weight: bold; font-size: 10pt;
            padding-bottom: 3pt; text-align: center;
        }

        /* --- Carrés section : inline-block + nowrap pour qu'aucun carré
             ne chevauche le code de la section voisine. Bordure légère,
             dimensions assorties à la hauteur de ligne. --- */
        .section-list { line-height: 1.6; }
        .carre-sup {
            display: inline-block;
            margin: 0 8pt 4pt 0;
            white-space: nowrap;
            font-size: 9pt;
            vertical-align: middle;
        }
        .carre {
            display: inline-block;
            width: 12pt; height: 12pt;
            border: 1px solid #000;
            margin-right: 3pt;
            text-align: center; vertical-align: middle;
            font-weight: bold; color: #fff;
            font-size: 8pt; line-height: 12pt;
        }
        .carre.active { background-color: gray; }

        /* --- Signatures --- */
        .signatures {
            margin-top: 16pt; width: 100%; border-collapse: collapse;
        }
        .signatures td {
            padding-top: 2pt; font-size: 8pt; color: #444;
        }
        .signatures .sig-line {
            border-top: 1px solid #000; padding-top: 2pt;
        }

        .footer-note {
            margin-top: 10pt; font-size: 7.5pt; color: #555;
            border-top: 1px solid #ccc; padding-top: 3pt; text-align: center;
        }
    </style>
</head>
<body>

    {{-- ====================== EN-TÊTE LEGACY ====================== --}}
    <table class="header">
        <tr>
            <td class="ministere">
                MINISTÈRE DE L'ENSEIGNEMENT SUPÉRIEUR<br>
                DE LA RECHERCHE SCIENTIFIQUE ET DE<br>
                LA FORMATION DES CADRES<br>
                <strong>------------------</strong><br>
                UNIVERSITÉ DES SCIENCES ET TECHNIQUES DE MASUKU<br>
                <strong>------------------</strong>
                @if($ustmImg)
                    <br><img src="{{ $ustmImg }}" style="width: 50pt; margin-top: 2pt;">
                @endif
            </td>
            <td class="photo">
                <div class="photo-frame">
                    @if(! empty($photoData))
                        <img src="{{ $photoData }}" alt="Photo du candidat">
                    @else
                        <div class="photo-missing">Photo<br>non disponible</div>
                    @endif
                </div>
            </td>
            <td class="sceau">
                @if($sceauImg)
                    <img src="{{ $sceauImg }}" style="max-width: 95pt; max-height: 95pt;">
                @endif
            </td>
        </tr>
    </table>

    {{-- ====================== TITRE ====================== --}}
    <div class="formulaire">
        <h3>FORMULAIRE D'INSCRIPTION AU CONCOURS</h3>
        <span class="matricule-band">{{ $candidat->matricule_public }}</span>
    </div>

    {{-- ====================== ÉTAT CIVIL ======================
         Disposé en grille 4 colonnes (label/val/label/val) pour gagner
         de la hauteur tout en restant lisible. Inclut maintenant
         email, "déjà BAC ?" et année du BAC. --}}
    <div class="etat-civil">
        <div class="ec-title"><span class="underline">ÉTAT CIVIL</span></div>
        <table class="kv-grid">
            <tr>
                <td class="lbl">Nom :</td>
                <td class="val"><span class="underline">{{ mb_strtoupper((string) $candidat->nom) }}</span></td>
                <td class="lbl">Prénom :</td>
                <td class="val"><span class="underline">{{ $candidat->prenom }}</span></td>
            </tr>
            <tr>
                <td class="lbl">Né(e) le :</td>
                <td class="val"><span class="underline">{{ optional($candidat->date_naissance)->format('d/m/Y') ?? '—' }}</span></td>
                <td class="lbl">Lieu :</td>
                <td class="val"><span class="underline">{{ $candidat->lieu_naissance ?? '—' }}</span></td>
            </tr>
            <tr>
                <td class="lbl">Sexe :</td>
                <td class="val"><span class="underline">{{ $candidat->sexe === 'F' ? 'Féminin' : 'Masculin' }}</span></td>
                <td class="lbl">Nationalité :</td>
                <td class="val"><span class="underline">{{ $candidat->nationalite?->nom ?? '—' }}</span></td>
            </tr>
            <tr>
                <td class="lbl">Téléphone :</td>
                <td class="val"><span class="underline">{{ $candidat->telephone ?? '—' }}</span></td>
                <td class="lbl">Email :</td>
                <td class="val"><span class="underline">{{ $candidat->email ?? '—' }}</span></td>
            </tr>
            <tr>
                <td class="lbl">Déjà BAC ?</td>
                <td class="val">
                    <span class="underline">{{ $candidat->deja_bac ? 'Oui' : 'Non' }}</span>
                </td>
                <td class="lbl">Année BAC :</td>
                <td class="val">
                    <span class="underline">{{ $candidat->deja_bac && $candidat->annee_bac ? $candidat->annee_bac : '—' }}</span>
                </td>
            </tr>
            <tr>
                <td class="lbl">Série BAC :</td>
                <td class="val" colspan="3"><span class="underline">{{ $bacLibelle }}</span></td>
            </tr>
        </table>
    </div>

    {{-- ====================== ÉTABLISSEMENT D'OBTENTION DU BAC ====================== --}}
    <table class="boxed" style="margin-top: 6pt;">
        <tr>
            <td style="font-weight: bold; background: #f8f8f8; width: 38%;">
                ÉTABLISSEMENT FRÉQUENTÉ
            </td>
            <td>{{ $candidat->etablissement_frequente ?? '—' }}</td>
        </tr>
    </table>

    {{-- ====================== CHOIX DE FORMATION ======================
         Le logo CUK est placé AU-DESSUS du tableau cycle/sections,
         centré, avec en dessous le titre puis le tableau pleine largeur. --}}
    <div style="text-align: center; margin-top: 10pt;">
        @if($logoImg)
            <img src="{{ $logoImg }}" alt="Logo CUK"
                 style="width: 70pt; height: 70pt; display: block; margin: 0 auto 4pt;">
        @endif
        <div style="font-weight: bold; font-size: 11pt;">
            Centre Universitaire de Koulamoutou ( CUK )
        </div>
    </div>

    <table class="boxed" style="margin-top: 4pt;">
        @foreach($cycles as $cycle)
            <tr>
                <td style="width: 32%; font-weight: bold; font-size: 9pt;">
                    {{ $cycle->nom }} ({{ $cycle->code }})
                </td>
                <td class="section-list">
                    @foreach($cycle->sections as $section)
                        @php
                            $isFirst  = (string) $section->id === (string) $idSect1;
                            $isSecond = $hasSecondChoice && (string) $section->id === (string) $idSect2;
                            $label    = $isFirst && $hasSecondChoice ? '1'
                                      : ($isSecond ? '2' : '');
                        @endphp
                        <span class="carre-sup">
                            <span class="carre @if($isFirst || $isSecond) active @endif">{{ $label }}</span>{{ $section->code }}
                        </span>
                    @endforeach
                </td>
            </tr>
        @endforeach
    </table>

    {{-- ====================== SESSION / CENTRE / STATUT (compacté) ====================== --}}
    <table class="boxed" style="margin-top: 8pt;">
        <tr>
            <td style="font-weight: bold; width: 22%;">Session</td>
            <td>
                {{ $candidat->session?->libelle ?? $candidat->session?->code ?? '—' }}
                @if($candidat->session?->date_concours)
                    &middot; épreuve du <strong>{{ $candidat->session->date_concours->format('d/m/Y') }}</strong>
                @endif
            </td>
            <td style="font-weight: bold; width: 18%;">Statut</td>
            <td style="width: 18%;"><strong>{{ $statutLabel }}</strong></td>
        </tr>
        <tr>
            <td style="font-weight: bold;">Centre d'examen</td>
            <td colspan="3">
                {{ $candidat->centre?->nom ?? '—' }}
                @if($candidat->centre?->adresse) &middot; {{ $candidat->centre->adresse }} @endif
                @if($candidat->centre?->ville)   &middot; {{ $candidat->centre->ville }}   @endif
            </td>
        </tr>
    </table>

    {{-- ====================== SIGNATURES (compactes) ====================== --}}
    <table class="signatures">
        <tr>
            <td style="width: 45%; height: 30pt;">&nbsp;</td>
            <td style="width: 10%;">&nbsp;</td>
            <td style="width: 45%; height: 30pt;">&nbsp;</td>
        </tr>
        <tr>
            <td class="sig-line">Signature du candidat</td>
            <td>&nbsp;</td>
            <td class="sig-line">Cachet du centre d'examen</td>
        </tr>
    </table>

    <div class="footer-note">
        Cette fiche doit être présentée le jour de l'épreuve avec une pièce d'identité officielle.
        Document généré le {{ now()->format('d/m/Y à H:i') }}.
        Toute falsification est passible de sanctions.
    </div>

</body>
</html>
