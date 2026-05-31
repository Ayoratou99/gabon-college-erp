{{-- Shared print-grade PDF wrapper for fiche & emploi-du-temps. --}}
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>@yield('title', 'CUK')</title>
    <style>
        @page { margin: 1.6cm 1.4cm; }
        body  { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 11pt; color: #1f2937; }
        h1    { font-size: 18pt; margin: 0 0 4pt; color: #0f172a; }
        h2    { font-size: 13pt; margin: 18pt 0 8pt; padding-bottom: 4pt;
                color: #1d4ed8; border-bottom: 2px solid #1d4ed8; }
        .doc-header {
            display: table; width: 100%;
            border-bottom: 3px double #1d4ed8; padding-bottom: 12pt; margin-bottom: 14pt;
        }
        .doc-header .left, .doc-header .right { display: table-cell; vertical-align: middle; }
        .doc-header .right { text-align: right; }
        .brand-mark {
            display: inline-block; background: #1d4ed8; color: #fff;
            padding: 6pt 10pt; border-radius: 6pt; font-weight: bold;
            font-size: 11pt; letter-spacing: .04em;
        }
        .doc-meta { font-size: 9pt; color: #64748b; }
        .matricule {
            display: inline-block; background: #f1f5f9; color: #0f172a;
            border: 1.5px solid #1d4ed8; padding: 6pt 12pt; border-radius: 6pt;
            font-weight: bold; font-family: Courier, monospace; letter-spacing: .04em;
        }
        table.kv { width: 100%; border-collapse: collapse; }
        table.kv th, table.kv td { text-align: left; padding: 5pt 8pt; vertical-align: top; }
        table.kv th {
            width: 32%; background: #f8fafc; color: #475569;
            font-weight: bold; font-size: 9pt; text-transform: uppercase; letter-spacing: .04em;
            border-bottom: 1px solid #e2e8f0;
        }
        table.kv td { border-bottom: 1px solid #e2e8f0; }
        table.data {
            width: 100%; border-collapse: collapse; margin-top: 4pt;
        }
        table.data th, table.data td {
            border: 1px solid #cbd5e1; padding: 6pt 8pt; font-size: 10pt;
        }
        table.data th { background: #1d4ed8; color: #fff; font-weight: bold; }
        table.data tr:nth-child(even) td { background: #f8fafc; }
        .footer-band {
            position: fixed; bottom: -1cm; left: 0; right: 0;
            text-align: center; font-size: 8pt; color: #94a3b8;
            border-top: 1px solid #cbd5e1; padding-top: 4pt;
        }
        .badge {
            display: inline-block; padding: 3pt 8pt; border-radius: 4pt;
            font-weight: bold; font-size: 9pt; letter-spacing: .04em; text-transform: uppercase;
        }
        .badge-valid  { background: #16a34a; color: #fff; }
        .badge-oui    { background: #f59e0b; color: #fff; }
        .badge-non    { background: #94a3b8; color: #fff; }
        .badge-rejete { background: #dc2626; color: #fff; }
        .badge-admis  { background: #1d4ed8; color: #fff; }
        .small        { font-size: 9pt; color: #64748b; }
    </style>
</head>
<body>

<div class="doc-header">
    <div class="left">
        <span class="brand-mark">CUK</span>
        <div style="margin-top:4pt; font-weight:bold;">Centre Universitaire de Koulamoutou</div>
        <div class="doc-meta">Concours d'entrée — République Gabonaise</div>
    </div>
    <div class="right">
        <div style="font-weight:bold; font-size:13pt;">@yield('doc-title')</div>
        <div class="doc-meta">Édité le {{ now()->format('d/m/Y à H:i') }}</div>
        @hasSection('matricule')
            <div style="margin-top:6pt;"><span class="matricule">@yield('matricule')</span></div>
        @endif
    </div>
</div>

@yield('content')

<div class="footer-band">
    Centre Universitaire de Koulamoutou — Document généré automatiquement.
    Toute falsification est passible de sanctions.
</div>

</body>
</html>
