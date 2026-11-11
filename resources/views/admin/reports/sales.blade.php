@extends('layouts.admin')
@section('title', 'Sales Report')

@section('content')
@php
    use App\Models\Order;
    $rp = fn ($v) => 'Rp ' . number_format($v, 0, ',', '.');
@endphp

@include('admin.reports._header', ['title' => 'Financial & Sales Report', 'subtitle' => 'Pendapatan toko, jumlah pesanan, dan detail transaksi.'])

<div class="row g-3 mb-4">
    <div class="col-6 col-xl-3"><div class="kpi accent">
        <div class="label">Total Pendapatan</div>
        <div class="value">{{ $rp($summary['revenue']) }}</div>
        <div class="note text-muted">{{ $summary['items'] }} item terjual</div>
    </div></div>
    <div class="col-6 col-xl-3"><div class="kpi">
        <div class="label">Pesanan Lunas</div>
        <div class="value">{{ $summary['orders'] }}</div>
        <div class="note text-muted">{{ $summary['cancelled'] }} dibatalkan</div>
    </div></div>
    <div class="col-6 col-xl-3"><div class="kpi">
        <div class="label">Rata-rata Nilai Order</div>
        <div class="value">{{ $rp($summary['average']) }}</div>
        <div class="note text-muted">per pesanan lunas</div>
    </div></div>
    <div class="col-6 col-xl-3"><div class="kpi">
        <div class="label">Belum Dibayar</div>
        <div class="value">{{ $rp($summary['unpaid_value']) }}</div>
        <div class="note text-muted">pesanan menunggu pembayaran</div>
    </div></div>
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
                <tr><th>No. Pesanan</th><th>Tanggal Lunas</th><th>Customer</th><th>Kota</th><th class="text-end">Total</th><th>Status</th></tr>
            </thead>
            <tbody>
            @forelse($transactions as $o)
                <tr>
                    <td><a href="{{ route('admin.orders.show', $o) }}" class="fw-semibold text-decoration-none">{{ $o->order_number }}</a></td>
                    <td class="small">{{ $o->paid_at?->format('d M Y, H:i') }}</td>
                    <td>{{ $o->user?->name }}</td>
                    <td class="small text-muted">{{ $o->delivery_city }}</td>
                    <td class="text-end fw-semibold">{{ $rp($o->total_amount) }}</td>
                    <td><span class="badge-soft {{ Order::STATUS_BADGES[$o->status] ?? 'secondary' }}">{{ Order::STATUS_LABELS[$o->status] ?? $o->status }}</span></td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center text-muted py-4">Tidak ada transaksi lunas pada periode ini.</td></tr>
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
