@extends('layouts.admin')
@section('title', 'Dashboard')

@section('content')
@php
    use App\Models\Order;
    $rp = fn ($v) => 'Rp ' . number_format($v, 0, ',', '.');
    $maxOcc = max(1, (int) $occasions->max('total'));
    $occTotal = max(1, (int) $occasions->sum('total'));
@endphp

<div class="admin-head d-flex flex-wrap justify-content-between align-items-end gap-2">
    <div>
        <h1 class="h3 mb-1 text-uppercase" style="letter-spacing:.04em">Studio Dashboard & Real-Time Metrics</h1>
        <p>{{ now()->locale('id')->translatedFormat('l, d F Y') }}</p>
    </div>
    @if($awaiting)
        <a href="{{ route('admin.orders.index', ['payment_status' => 'proof_uploaded']) }}" class="btn btn-outline-plum btn-sm">
            {{ $awaiting }} pembayaran perlu diverifikasi
        </a>
    @endif
</div>

{{-- KPI --}}
<div class="row g-3 mb-4">
    <div class="col-6 col-xl-3">
        <div class="kpi accent">
            <div class="label">Total Pendapatan</div>
            <div class="value">{{ $rp($revenue) }}</div>
            <div class="note text-success">{{ $rp($revenueToday) }} hari ini</div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="kpi">
            <div class="label">Pesanan Aktif</div>
            <div class="value">{{ $activeOrders }} Orders</div>
            <div class="note text-muted">{{ $preparing }} sedang dirangkai · {{ $orders }} total</div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="kpi">
            <div class="label">Customer & Katalog</div>
            <div class="value">{{ $customers }} Customer</div>
            <div class="note text-muted">{{ $bouquets }} bouquet terdaftar</div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="kpi {{ $lowStockCount ? 'alert-kpi' : '' }}">
            <div class="label">Flower Stock Alert</div>
            <div class="value">{{ $lowStockCount }} Item</div>
            <div class="note {{ $lowStockCount ? 'text-danger' : 'text-success' }}">
                {{ $lowStockCount ? 'Perlu restock' : 'Semua stok aman' }}
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    {{-- Pesanan terbaru --}}
    <div class="col-xl-8">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h2 class="section-title h6 mb-0">Live Orders & Fulfillment Status</h2>
            <a href="{{ route('admin.orders.index') }}" class="small">Lihat semua</a>
        </div>
        <div class="card">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead><tr><th>Order / Penerima</th><th>Bouquet & Jadwal</th><th class="text-end">Status</th></tr></thead>
                    <tbody>
                    @forelse($recentOrders as $order)
                        <tr>
                            <td>
                                <a href="{{ route('admin.orders.show', $order) }}" class="fw-semibold text-decoration-none">{{ $order->order_number }}</a>
                                <div class="small text-muted">{{ $order->recipient_name }}</div>
                            </td>
                            <td class="small">
                                {{ Str::limit($order->items->map(fn ($i) => $i->bouquet_name ?? $i->flower_name)->filter()->join(', '), 40) }}
                                <div class="text-muted">{{ $order->delivery_date->format('d M') }} · {{ ucfirst($order->delivery_slot ?? '-') }}</div>
                            </td>
                            <td class="text-end">
                                <span class="badge-soft {{ Order::STATUS_BADGES[$order->status] ?? 'secondary' }}">
                                    {{ Order::STATUS_LABELS[$order->status] ?? $order->status }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="text-center text-muted py-4">Belum ada pesanan.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Preferensi --}}
    <div class="col-xl-4">
        <h2 class="section-title h6 mb-2">Customer Preferences</h2>
        <div class="panel">
            <div class="small fw-semibold mb-3">Momen paling sering dipesan</div>
            @forelse($occasions as $occ)
                <div class="mb-3">
                    <div class="d-flex justify-content-between small mb-1">
                        <span>{{ ucwords(str_replace('-', ' ', $occ->occasion)) }}</span>
                        <span class="text-muted">{{ round($occ->total / $occTotal * 100) }}%</span>
                    </div>
                    <div class="bar"><span style="width: {{ $occ->total / $maxOcc * 100 }}%"></span></div>
                </div>
            @empty
                <p class="small text-muted">Belum ada data momen.</p>
            @endforelse

            <div class="small fw-semibold mt-4 mb-2">Bouquet terlaris</div>
            @forelse($bestSellers as $i => $item)
                <div class="mini-card d-flex justify-content-between small py-2">
                    <span>{{ $i + 1 }}. {{ $item->bouquet_name }}</span>
                    <span class="text-mauve">{{ $item->qty }} terjual</span>
                </div>
            @empty
                <p class="small text-muted mb-0">Belum ada penjualan.</p>
            @endforelse
        </div>
    </div>
</div>

{{-- Stok --}}
@if($lowStock->isNotEmpty())
    <div class="d-flex justify-content-between align-items-center mt-4 mb-2">
        <h2 class="section-title h6 mb-0">Peringatan stok ({{ $lowStockCount }} bouquet)</h2>
        <a href="{{ route('admin.bouquets.index', ['stock' => 'low']) }}" class="small">Lihat semua</a>
    </div>
    <div class="card">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr><th>Bouquet</th><th>Stok</th><th>Batas minimum</th><th class="text-end">Status / Aksi</th></tr></thead>
                <tbody>
                @foreach($lowStock as $item)
                    <tr>
                        <td>{{ $item->name }}</td>
                        <td>{{ $item->stock }}</td>
                        <td class="text-muted">{{ $item->low_stock_threshold }}</td>
                        <td class="text-end">
                            <span class="badge-soft {{ $item->stock <= 0 ? 'danger' : 'warning' }} me-2">
                                {{ $item->stock <= 0 ? 'Habis' : 'Sisa ' . $item->stock }}
                            </span>
                            <a href="{{ route('admin.bouquets.edit', $item) }}" class="btn btn-sm btn-outline-plum">Restock</a>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif
@endsection
