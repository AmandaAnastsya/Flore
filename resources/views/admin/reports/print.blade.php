<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} · {{ config('flore.store.name', 'Floré') }}</title>
    <style>
        body { margin: 0; font-family: "DM Sans", system-ui, sans-serif; color: #2e2224; font-size: 10pt; background: #fff; }
        .wrap { padding: 12mm; }
        .toolbar { text-align: center; padding: .8rem; background: #f4eef0; }
        .toolbar button { background: #4a2a30; color: #fff; border: 0; border-radius: .35rem; padding: .5rem 1.2rem; cursor: pointer; }
        header { display: flex; justify-content: space-between; align-items: flex-end; border-bottom: 2px solid #4a2a30; padding-bottom: 3mm; margin-bottom: 5mm; }
        .brand { font-family: Georgia, serif; font-size: 20pt; font-weight: 700; color: #4a2a30; }
        h1 { font-size: 13pt; margin: 0; }
        .muted { color: #7d6d71; font-size: 9pt; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #4a2a30; color: #fff; text-align: left; padding: 2mm; font-size: 8.5pt; }
        td { padding: 1.6mm 2mm; border-bottom: 1px solid #ead8dd; font-size: 8.5pt; vertical-align: top; }
        tr:nth-child(even) td { background: #fbf6f7; }
        td.num { text-align: right; white-space: nowrap; }
        @page { size: A4 landscape; margin: 10mm; }
        @media print { .toolbar { display: none; } .wrap { padding: 0; } thead { display: table-header-group; } tr { break-inside: avoid; } }
    </style>
</head>
<body>
<div class="toolbar"><button onclick="window.print()">Cetak / Simpan PDF</button></div>
<div class="wrap">
    <header>
        <div>
            <div class="brand">{{ config('flore.store.name', 'Floré') }}</div>
            <h1>{{ $title }}</h1>
        </div>
        <div class="muted" style="text-align:right">Periode: {{ $period }}<br>Dicetak {{ now()->format('d M Y H:i') }} · {{ count($rows) }} baris</div>
    </header>
    <table>
        <thead><tr>@foreach($header as $h)<th>{{ $h }}</th>@endforeach</tr></thead>
        <tbody>
        @forelse($rows as $row)
            <tr>
                @foreach(array_values((array) $row) as $v)
                    <td class="{{ is_int($v) || is_float($v) || (is_string($v) && is_numeric($v)) ? 'num' : '' }}">
                        {{ is_int($v) || is_float($v) || (is_string($v) && is_numeric($v) && ! preg_match('/^0\d/', $v)) ? number_format((float) $v, fmod((float) $v, 1) ? 1 : 0, ',', '.') : $v }}
                    </td>
                @endforeach
            </tr>
        @empty
            <tr><td colspan="{{ count($header) }}" style="text-align:center;padding:6mm" class="muted">Tidak ada data pada periode ini.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
</body>
</html>
