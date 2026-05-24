<!doctype html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>{{ $title }}</title>
    <style>
        @page { margin: 12mm 10mm; }
        body  { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #1f2937; }
        h1    { font-size: 14px; margin: 0 0 4px; color: #1d4ed8; }
        .meta { font-size: 8.5px; color: #6b7280; margin-bottom: 12px; }
        .meta strong { color: #111827; }
        table { width: 100%; border-collapse: collapse; }
        th    {
            background: #1d4ed8; color: #fff;
            padding: 5px 6px; text-align: left;
            font-size: 9px; text-transform: uppercase; letter-spacing: .03em;
        }
        td    { padding: 4px 6px; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
        tbody tr:nth-child(even) td { background: #f9fafb; }
        .right { text-align: right; }
        .center{ text-align: center; }
        .footer {
            position: fixed; bottom: 4mm; left: 10mm; right: 10mm;
            font-size: 8px; color: #9ca3af;
            border-top: 1px solid #e5e7eb;
            padding-top: 4px;
            display: flex; justify-content: space-between;
        }
    </style>
</head>
<body>
    <h1>{{ $title }}</h1>

    <div class="meta">
        @foreach($meta as $label => $value)
            <strong>{{ $label }}&nbsp;:</strong> {{ $value }} &nbsp;·&nbsp;
        @endforeach
        <strong>Généré le&nbsp;:</strong> {{ $generatedAt->format('d/m/Y à H:i') }}
        &nbsp;·&nbsp;<strong>Lignes&nbsp;:</strong> {{ count($rows) }}@if($truncated) (tronqué) @endif
    </div>

    <table>
        <thead>
            <tr>
                @foreach ($columns as $col)
                    <th class="{{ $col->align === 'right' ? 'right' : ($col->align === 'center' ? 'center' : '') }}">
                        {{ $col->header }}
                    </th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    @foreach ($columns as $i => $col)
                        @php
                            $value = $row[$i];
                            if ($value instanceof \DateTimeInterface) {
                                $value = $value->format($col->format === 'date' ? 'd/m/Y' : 'd/m/Y H:i');
                            } elseif (is_bool($value)) {
                                $value = $value ? 'Oui' : 'Non';
                            }
                        @endphp
                        <td class="{{ $col->align === 'right' ? 'right' : ($col->align === 'center' ? 'center' : '') }}">
                            {{ $value }}
                        </td>
                    @endforeach
                </tr>
            @empty
                <tr><td colspan="{{ count($columns) }}" class="center" style="padding: 20px; color: #9ca3af;">
                    Aucune donnée à exporter.
                </td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        <span>{{ config('app.name') }} — {{ $title }}</span>
        <span>{{ $generatedAt->format('d/m/Y H:i') }}</span>
    </div>
</body>
</html>
