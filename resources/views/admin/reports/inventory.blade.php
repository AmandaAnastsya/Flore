@extends('layouts.admin')
@section('title', 'Inventory Report')

@section('content')
@php
    use App\Models\StockMovement;
    $rp = fn ($v) => 'Rp ' . number_format($v, 0, ',', '.');
    $s = $summary;
@endphp

@include('admin.reports._header', ['title' => 'Inventory Report', 'subtitle' => 'Nilai persediaan, item yang perlu restock, dan riwayat pergerakan stok.'])

<div class="row g-3 mb-4">
    <div class="col-6 col-lg-4 col-xl"><div class="kpi accent">
        <div class="label">Nilai Persediaan (HPP)</div>
        <div class="value">{{ $rp($s['value']) }}</div>
        <div class="note text-muted">{{ $s['uncosted'] ? $s['uncosted'] . ' item ber-stok belum punya HPP' : 'semua item ber-HPP' }} · saat ini</div>
    </div></div>
    <div class="col-6 col-lg-4 col-xl"><div class="kpi {{ $s['critical'] ? 'alert-kpi' : '' }}">
        <div class="label">Perlu Restock</div>
        <div class="value">{{ $s['critical'] }} Item</div>
        <div class="note text-muted">bouquet, bunga & supplies aktif</div>
    </div></div>
    <div class="col-6 col-lg-4 col-xl"><div class="kpi">
        <div class="label">Barang Masuk</div>
        <div class="value">{{ number_format($s['restock']['qty'], 0, ',', '.') }} Unit</div>
        <div class="note text-muted">{{ $s['restock']['count'] }}× restock · {{ $rp($s['restock']['value']) }}</div>
    </div></div>
    <div class="col-6 col-lg-4 col-xl"><div class="kpi {{ $s['waste']['qty'] ? 'alert-kpi' : '' }}">
        <div class="label">Dibuang (Layu/Rusak)</div>
        <div class="value">{{ number_format($s['waste']['qty'], 0, ',', '.') }} Unit</div>
        <div class="note text-muted">kerugian ± {{ $rp($s['waste']['value']) }}</div>
    </div></div>
    <div class="col-6 col-lg-4 col-xl"><div class="kpi">
        <div class="label">Terjual</div>
        <div class="value">{{ $s['sold_bouquets'] }} Bouquet</div>
        <div class="note text-muted">+ {{ number_format($s['sold_stems'], 0, ',', '.') }} tangkai bunga</div>
    </div></div>
</div>

<h2 class="section-title h6 mb-2">Status Persediaan Saat Ini</h2>
<div class="card mb-4">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr><th>Jenis</th><th>Item</th><th class="text-end">Stok</th><th class="text-end">Batas</th><th>Status</th><th class="text-end">HPP / unit</th><th class="text-end">Nilai</th></tr></thead>
            <tbody>
            @forelse($rows as $r)
                <tr class="{{ $r['active'] ? '' : 'text-muted' }}">
                    <td class="small text-muted">{{ $r['type'] }}</td>
                    <td>{{ $r['name'] }} @unless($r['active']) <span class="badge bg-secondary">Nonaktif</span> @endunless</td>
                    <td class="text-end">{{ number_format($r['stock'], 0, ',', '.') }} <span class="small text-muted">{{ $r['unit'] }}</span></td>
                    <td class="text-end small">{{ $r['reorder'] }}</td>
                    <td><span class="badge-soft {{ $r['critical'] ? 'danger' : 'success' }}">{{ $r['critical'] ? 'Perlu restock' : 'Aman' }}</span></td>
                    <td class="text-end small">{{ $r['cost'] === null ? '—' : $rp($r['cost']) }}</td>
                    <td class="text-end">{{ $r['value'] === null ? '—' : $rp($r['value']) }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center text-muted py-4">Belum ada data persediaan.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<h2 class="section-title h6 mb-2">Riwayat Pergerakan Stok (periode terpilih)</h2>
<div class="card">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr><th>Waktu</th><th>Item</th><th>Jenis</th><th class="text-end">Perubahan</th><th class="text-end">Stok akhir</th><th>Catatan</th><th>Oleh</th></tr></thead>
            <tbody>
            @forelse($movements as $m)
                <tr>
                    <td class="small">{{ $m->created_at->format('d M Y H:i') }}</td>
                    <td>{{ $m->item_name }} <div class="small text-muted">{{ StockMovement::ITEM_TYPES[$m->item_type] ?? $m->item_type }}</div></td>
                    <td><span class="badge-soft {{ ['restock' => 'success', 'waste' => 'danger', 'adjustment' => 'secondary'][$m->reason] }}">{{ StockMovement::REASONS[$m->reason] }}</span></td>
                    <td class="text-end fw-semibold {{ $m->change < 0 ? 'text-danger' : 'text-success' }}">{{ $m->change > 0 ? '+' : '' }}{{ $m->change }}</td>
                    <td class="text-end">{{ $m->stock_after }}</td>
                    <td class="small">{{ $m->note ?? '—' }}</td>
                    <td class="small text-muted">{{ $m->user?->name ?? 'Sistem' }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center text-muted py-4">Belum ada restock, koreksi, atau barang dibuang pada periode ini.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $movements->links() }}</div>
<p class="small text-muted">Stok berkurang karena penjualan dihitung dari pesanan lunas ("Terjual"). Ekspor CSV berisi status persediaan saat ini.</p>
@endsection
