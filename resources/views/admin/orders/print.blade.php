<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Label & Kartu {{ $order->order_number }} · Floré</title>
    <style>
        :root { --plum: #4a2a30; --mauve: #8e5a66; --line: #e6d3d8; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: "DM Sans", system-ui, sans-serif; color: #2e2224; background: #f4eef0; }
        .toolbar { display: flex; gap: .5rem; justify-content: center; padding: 1rem; }
        .toolbar button { background: var(--plum); color: #fff; border: 0; border-radius: .35rem; padding: .55rem 1.2rem; font-size: .9rem; cursor: pointer; }
        .sheet { width: 190mm; margin: 0 auto 2rem; display: grid; gap: 10mm; }
        .label, .card { background: #fff; border: 1px dashed #b9a0a7; padding: 8mm; }
        .brand { font-family: Georgia, "Playfair Display", serif; font-weight: 700; color: var(--plum); font-size: 22pt; }
        .muted { color: #7d6d71; font-size: 9pt; text-transform: uppercase; letter-spacing: .08em; }
        .label h2 { font-size: 15pt; margin: 2mm 0 1mm; }
        .label table { width: 100%; border-collapse: collapse; font-size: 10pt; margin-top: 4mm; }
        .label td { padding: 1.5mm 0; border-bottom: 1px solid var(--line); vertical-align: top; }
        .label td:first-child { width: 32mm; color: #7d6d71; }
        .order-no { font-family: monospace; font-size: 14pt; font-weight: 700; letter-spacing: .05em; }
        .card { min-height: 95mm; display: flex; flex-direction: column; justify-content: center; text-align: center; border-color: var(--mauve); }
        .card .msg { font-family: Georgia, serif; font-style: italic; font-size: 16pt; line-height: 1.5; margin: 8mm 6mm; color: var(--plum); }
        .card .from { font-size: 11pt; color: var(--mauve); }
        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .sheet { margin: 0; width: auto; }
            .label, .card { break-inside: avoid; }
        }
    </style>
</head>
<body>
@php
    $date = $order->delivery_date->locale('id')->translatedFormat('l, d F Y');
    $items = $order->items->map(fn ($i) => $i->quantity . '× ' . $i->name . ($i->isStem() ? ' (tangkai)' : ''))->implode(', ');
@endphp
<div class="toolbar">
    <button onclick="window.print()">Cetak / Simpan PDF</button>
</div>

<div class="sheet">
    {{-- Label pengiriman --}}
    <section class="label">
        <div style="display:flex;justify-content:space-between;align-items:flex-start">
            <div>
                <div class="brand">{{ config('flore.store.name', 'Floré') }}</div>
                <div class="muted">Label pengiriman</div>
            </div>
            <div style="text-align:right">
                <div class="order-no">{{ $order->order_number }}</div>
                <div class="muted">{{ $date }}</div>
            </div>
        </div>
        <h2>Untuk: {{ $order->recipient_name }}</h2>
        <table>
            <tr><td>Telepon</td><td>{{ $order->recipient_phone }}</td></tr>
            <tr><td>Alamat</td><td>{{ $order->delivery_address }}, {{ $order->delivery_city }}</td></tr>
            <tr><td>Jadwal</td><td>{{ $date }} · {{ $slots[$order->delivery_slot] ?? '-' }}</td></tr>
            <tr><td>Isi paket</td><td>{{ $items }}</td></tr>
            <tr><td>Kurir</td><td>{{ $order->courier ? $order->courier->name . ' · ' . $order->courier->vehicleLabel() : '—' }}</td></tr>
            <tr><td>Florist</td><td>{{ $order->florist?->name ?? '—' }}</td></tr>
            @if($order->production_note) <tr><td>Catatan</td><td>{{ $order->production_note }}</td></tr> @endif
            <tr><td>Dari</td><td>{{ config('flore.store.name', 'Floré') }} · {{ config('flore.studio.address') }}{{ config('flore.store.phone') ? ' · ' . config('flore.store.phone') : '' }}</td></tr>
        </table>
    </section>

    {{-- Kartu ucapan --}}
    <section class="card">
        <div class="brand" style="font-size:18pt">{{ config('flore.store.name', 'Floré') }}</div>
        @if($order->occasion) <div class="muted" style="margin-top:2mm">{{ ucwords(str_replace('-', ' ', $order->occasion)) }}</div> @endif
        <div class="msg">{{ $order->card_message ? '“' . $order->card_message . '”' : 'With love, for you.' }}</div>
        <div class="from">— {{ $order->is_anonymous ? 'Seseorang yang menyayangimu' : ($order->sender_name ?: $order->user?->name) }}</div>
    </section>
</div>
</body>
</html>
