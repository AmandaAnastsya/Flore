<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Invoice {{ $order->order_number }} · {{ config('flore.store.name', 'Floré') }}</title>
    <style>
        :root { --plum: #4a2a30; --mauve: #8e5a66; --line: #ead8dd; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: "DM Sans", system-ui, sans-serif; color: #2e2224; background: #f4eef0; font-size: 10.5pt; }
        .toolbar { display: flex; gap: .5rem; justify-content: center; padding: 1rem; flex-wrap: wrap; }
        .toolbar button, .toolbar a { background: var(--plum); color: #fff; border: 0; border-radius: .35rem; padding: .55rem 1.1rem; font-size: .9rem; cursor: pointer; text-decoration: none; }
        .toolbar a { background: #fff; color: var(--plum); border: 1px solid var(--plum); }
        .sheet { background: #fff; width: min(210mm, 100%); margin: 0 auto 2rem; padding: 14mm; position: relative; }
        header { display: flex; justify-content: space-between; gap: 1rem; border-bottom: 2px solid var(--plum); padding-bottom: 5mm; flex-wrap: wrap; }
        .brand { font-family: Georgia, "Playfair Display", serif; font-weight: 700; color: var(--plum); font-size: 24pt; line-height: 1; }
        .muted { color: #7d6d71; font-size: 9pt; }
        h1 { font-size: 15pt; margin: 0 0 1mm; color: var(--plum); letter-spacing: .06em; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 6mm; margin: 6mm 0; }
        .box h2 { font-size: 8.5pt; text-transform: uppercase; letter-spacing: .1em; color: var(--mauve); margin: 0 0 1.5mm; }
        table { width: 100%; border-collapse: collapse; margin-top: 2mm; }
        th { text-align: left; font-size: 8.5pt; text-transform: uppercase; letter-spacing: .06em; color: #fff; background: var(--plum); padding: 2.2mm; }
        td { padding: 2.2mm; border-bottom: 1px solid var(--line); vertical-align: top; }
        .num { text-align: right; white-space: nowrap; }
        .totals { width: 70mm; margin-left: auto; margin-top: 4mm; }
        .totals td { border: 0; padding: 1.2mm 2mm; }
        .totals tr.grand td { border-top: 2px solid var(--plum); font-weight: 700; font-size: 12pt; color: var(--plum); }
        .stamp { position: absolute; top: 40mm; right: 18mm; transform: rotate(-12deg); border: 3px solid; border-radius: 6px; padding: 2mm 5mm;
                 font-weight: 800; font-size: 18pt; letter-spacing: .12em; opacity: .85; }
        .stamp.paid { color: #4a7151; border-color: #4a7151; }
        .stamp.unpaid { color: #b5482f; border-color: #b5482f; }
        .stamp.void { color: #8a7b7e; border-color: #8a7b7e; }
        .pay { background: #fbf3f5; border: 1px solid var(--line); border-radius: 4px; padding: 4mm; margin-top: 6mm; }
        footer { margin-top: 8mm; border-top: 1px solid var(--line); padding-top: 3mm; }
        @media (max-width: 640px) { .grid { grid-template-columns: 1fr; } .sheet { padding: 6mm; } .stamp { top: 52mm; font-size: 13pt; } }
        @media print { body { background: #fff; } .toolbar { display: none; } .sheet { margin: 0; width: auto; padding: 0; } }
    </style>
</head>
<body>
@php
    $rp = fn ($v) => 'Rp ' . number_format((float) $v, 0, ',', '.');
    $paid = $order->payment_status === 'confirmed' && $order->status !== 'cancelled';
    $stamp = $order->status === 'cancelled' ? ['void', 'DIBATALKAN'] : ($paid ? ['paid', 'LUNAS'] : ['unpaid', 'BELUM DIBAYAR']);
    $bank = config('flore.payment');
@endphp
<div class="toolbar">
    <button onclick="window.print()">Cetak / Simpan PDF</button>
    <a href="{{ $backUrl }}">Kembali ke pesanan</a>
</div>

<div class="sheet">
    <div class="stamp {{ $stamp[0] }}">{{ $stamp[1] }}</div>

    <header>
        <div>
            <div class="brand">{{ config('flore.store.name', 'Floré') }}</div>
            <div class="muted" style="margin-top:2mm">{{ config('flore.studio.address') }}</div>
            <div class="muted">{{ collect([config('flore.store.phone'), config('flore.store.email')])->filter()->implode(' · ') }}</div>
        </div>
        <div style="text-align:right">
            <h1>INVOICE</h1>
            <div><strong>INV/{{ $order->order_number }}</strong></div>
            <div class="muted">Tanggal: {{ $order->created_at->locale('id')->translatedFormat('d F Y') }}</div>
            @if($paid && $order->paid_at) <div class="muted">Lunas: {{ $order->paid_at->locale('id')->translatedFormat('d F Y, H:i') }}</div> @endif
        </div>
    </header>

    <div class="grid">
        <div class="box">
            <h2>Ditagihkan kepada</h2>
            <div><strong>{{ $order->user?->name ?? '—' }}</strong></div>
            <div class="muted">{{ $order->user?->email }}</div>
            <div class="muted">{{ $order->user?->phone }}</div>
        </div>
        <div class="box">
            <h2>Dikirim kepada</h2>
            <div><strong>{{ $order->recipient_name }}</strong> · {{ $order->recipient_phone }}</div>
            <div class="muted">{{ $order->delivery_address }}, {{ $order->delivery_city }}</div>
            <div class="muted">{{ $order->delivery_date->locale('id')->translatedFormat('l, d F Y') }} · {{ $slots[$order->delivery_slot] ?? '' }}</div>
        </div>
    </div>

    <table>
        <thead><tr><th>Item</th><th class="num">Harga</th><th class="num">Jumlah</th><th class="num">Subtotal</th></tr></thead>
        <tbody>
        @foreach($order->items as $item)
            <tr>
                <td>{{ $item->name }} @if($item->isStem()) <span class="muted">(per tangkai)</span> @endif</td>
                <td class="num">{{ $rp($item->price) }}</td>
                <td class="num">{{ $item->quantity }}</td>
                <td class="num">{{ $rp($item->subtotal) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr><td>Subtotal</td><td class="num">{{ $rp($order->subtotal) }}</td></tr>
        <tr><td>Ongkos kirim</td><td class="num">{{ (float) $order->delivery_fee > 0 ? $rp($order->delivery_fee) : 'Gratis' }}</td></tr>
        @if((float) $order->discount > 0) <tr><td>Diskon</td><td class="num">− {{ $rp($order->discount) }}</td></tr> @endif
        <tr class="grand"><td>Total</td><td class="num">{{ $rp($order->total_amount) }}</td></tr>
    </table>

    @if(! $paid && $order->status !== 'cancelled')
        <div class="pay">
            <strong>Cara pembayaran — transfer bank {{ $bank['bank'] }}</strong><br>
            No. rekening <strong>{{ $bank['account_number'] }}</strong> a.n. <strong>{{ $bank['account_name'] }}</strong><br>
            Jumlah <strong>{{ $rp($order->total_amount) }}</strong>. Unggah bukti transfer di halaman pesanan setelah membayar.
            @if($deadline = $order->paymentDeadline())<br>Batas pembayaran: <strong>{{ $deadline->locale('id')->translatedFormat('l, d F Y H:i') }}</strong> — setelah itu pesanan dibatalkan otomatis.@endif
        </div>
    @else
        <p class="muted" style="margin-top:6mm">Metode pembayaran: Transfer bank {{ $bank['bank'] }}.</p>
    @endif

    <footer class="muted">
        Terima kasih telah berbelanja di {{ config('flore.store.name', 'Floré') }}. Invoice ini dibuat otomatis dan sah tanpa tanda tangan.
    </footer>
</div>
</body>
</html>
