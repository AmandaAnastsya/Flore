@extends('layouts.admin')
@section('title', 'Sales Report')

@section('content')
@php
    use App\Models\Order;
    $rp = fn ($v) => 'Rp ' . number_format($v, 0, ',', '.');
@endphp

@include('admin.reports._header', ['title' => 'Financial & Sales Report', 'subtitle' => 'Analisis pendapatan, laba bersih, margin produk, dan detail transaksi.'])

@php
    $s = $summary;
    $max = max(1, $trend->max('revenue'), $trend->max(fn ($m) => abs($m['net_profit'] ?? 0)));
@endphp
<div class="row g-3 mb-3">
    <div class="col-6 col-xl-3"><div class="kpi accent">
        <div class="label">Total Pendapatan (Gross)</div>
        <div class="value">{{ $rp($s['revenue']) }}</div>
        <div class="note text-muted">{{ $s['orders'] }} pesanan lunas · {{ $s['items'] }} item</div>
    </div></div>
    <div class="col-6 col-xl-3"><div class="kpi" style="border-left:3px solid #6b8f71">
        <div class="label">Laba Bersih (Net Profit)</div>
        @if($s['net_profit'] === null)
            <div class="value">—</div>
            <div class="note text-muted"><a href="{{ route('admin.bouquets.index') }}">Isi HPP</a> untuk menghitung laba</div>
        @else
            <div class="value {{ $s['net_profit'] < 0 ? 'text-danger' : 'text-success' }}">{{ $rp($s['net_profit']) }}</div>
            <div class="note text-muted">Margin bersih {{ $s['net_margin'] }}%</div>
        @endif
    </div></div>
    <div class="col-6 col-xl-3"><div class="kpi" style="border-left:3px solid #c9a3ad">
        <div class="label">Rata-rata Nilai Order</div>
        <div class="value">{{ $rp($s['average']) }}</div>
        <div class="note text-muted">per pesanan lunas</div>
    </div></div>
    <div class="col-6 col-xl-3"><div class="kpi" style="border-left:3px solid #c4524a">
        <div class="label">Biaya Operasional & Stok</div>
        <div class="value">{{ $rp($s['expenses'] + $s['stock_purchases']) }}</div>
        <div class="note text-muted"><a href="{{ route('admin.reports.expenses') }}">Operasional {{ $rp($s['expenses']) }}</a> · belanja stok {{ $rp($s['stock_purchases']) }}</div>
    </div></div>
</div>
<div class="row g-3 mb-4 small">
    <div class="col-md-4"><div class="panel py-2">Laba kotor (pendapatan − HPP):
        <strong class="{{ ($s['gross_profit'] ?? 0) < 0 ? 'text-danger' : 'text-success' }}">{{ $s['gross_profit'] === null ? '—' : $rp($s['gross_profit']) . ' · ' . $s['margin'] . '%' }}</strong>
        @if($s['cost_coverage'] !== null && $s['cost_coverage'] < 100) <span class="text-muted">(dari {{ $s['cost_coverage'] }}% penjualan ber-HPP)</span> @endif
    </div></div>
    <div class="col-md-4"><div class="panel py-2">Belum dibayar: <strong>{{ $rp($s['unpaid_value']) }}</strong></div></div>
    <div class="col-md-4"><div class="panel py-2">Dibatalkan: <strong>{{ $s['cancelled'] }} pesanan</strong></div></div>
</div>

<div class="row g-4 mb-4">
    <div class="col-lg-8">
        <div class="panel h-100">
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
                <h2 class="panel-title mb-0">Tren Penjualan & Laba Bersih (Bulanan)</h2>
                <div class="small"><span class="legend-dot" style="background:#4a2a30"></span>Gross Revenue <span class="legend-dot ms-2" style="background:#6b8f71"></span>Net Profit</div>
            </div>
            <div class="month-chart" role="img" aria-label="Pendapatan dan laba bersih 6 bulan terakhir">
                @foreach($trend as $m)
                    <div class="month-col" tabindex="0">
                        <div class="month-bars">
                            <span class="mb revenue" style="height: {{ round($m['revenue'] / $max * 100) }}%"></span>
                            <span class="mb {{ ($m['net_profit'] ?? 0) < 0 ? 'loss' : 'profit' }}" style="height: {{ round(abs($m['net_profit'] ?? 0) / $max * 100) }}%"></span>
                        </div>
                        <div class="month-label">{{ $m['month']->locale('id')->translatedFormat('M') }}</div>
                        <div class="month-tip">
                            <strong>{{ $m['month']->locale('id')->translatedFormat('F Y') }}</strong><br>
                            Pendapatan {{ $rp($m['revenue']) }}<br>
                            Laba bersih {{ $m['net_profit'] === null ? '— (HPP belum lengkap)' : $rp($m['net_profit']) }}<br>
                            Biaya operasional {{ $rp($m['expenses']) }}
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="panel h-100">
            <h2 class="panel-title">Produk Terlaris & Margin</h2>
            @forelse($topProducts as $p)
                <div class="mb-3">
                    <div class="d-flex justify-content-between small"><strong>{{ $p['name'] }}</strong><span>{{ $rp($p['revenue']) }}</span></div>
                    <div class="bar my-1"><span style="width: {{ $p['share'] }}%"></span></div>
                    <div class="d-flex justify-content-between text-muted" style="font-size:.72rem">
                        <span>Kontribusi {{ $p['share'] }}%</span>
                        <span>Margin {{ $p['margin'] === null ? '—' : $p['margin'] . '%' }}</span>
                    </div>
                </div>
            @empty
                <p class="small text-muted mb-0">Belum ada penjualan lunas pada periode ini.</p>
            @endforelse
        </div>
    </div>
</div>

<h2 class="section-title h6 mb-2">Tren Pendapatan Harian</h2>
<div class="panel mb-4">
    @include('admin._bar-chart', ['series' => $series, 'label' => 'Pendapatan harian periode terpilih'])
</div>

<h2 class="section-title h6 mb-2">Laporan Transaksi Penjualan Detail</h2>
<div class="card">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr><th>No. Pesanan</th><th>Tanggal Lunas</th><th>Customer</th><th>Kota</th><th>Metode Pembayaran</th><th class="text-end">Total</th><th class="text-end">Laba Kotor</th><th>Status</th></tr>
            </thead>
            <tbody>
            @forelse($transactions as $o)
                <tr>
                    <td><a href="{{ route('admin.orders.show', $o) }}" class="fw-semibold text-decoration-none">{{ $o->order_number }}</a></td>
                    <td class="small">{{ $o->paid_at?->format('d M Y, H:i') }}</td>
                    <td>{{ $o->user?->name }}</td>
                    <td class="small text-muted">{{ $o->delivery_city }}</td>
                    <td class="small">Transfer {{ config('flore.payment.bank') }}</td>
                    <td class="text-end fw-semibold">{{ $rp($o->total_amount) }}</td>
                    @php $profit = $o->items->every(fn ($i) => $i->unit_cost !== null) ? $o->items->sum(fn ($i) => $i->grossProfit()) : null; @endphp
                    <td class="text-end {{ $profit === null ? 'text-muted' : ($profit < 0 ? 'text-danger' : 'text-success') }}">{{ $profit === null ? '—' : $rp($profit) }}</td>
                    <td><span class="badge-soft {{ Order::STATUS_BADGES[$o->status] ?? 'secondary' }}">{{ Order::STATUS_LABELS[$o->status] ?? $o->status }}</span></td>
                </tr>
            @empty
                <tr><td colspan="8" class="text-center text-muted py-4">Tidak ada transaksi lunas pada periode ini.</td></tr>
            @endforelse
            </tbody>
            @if($transactions->total())
                <tfoot>
                    <tr class="table-light">
                        <td colspan="4" class="small">Total terfilter ({{ $summary['orders'] }} transaksi)</td>
                        <td class="text-end fw-bold">{{ $rp($summary['revenue']) }}</td>
                        <td></td>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>
</div>
<div class="mt-3">{{ $transactions->links() }}</div>
@endsection
