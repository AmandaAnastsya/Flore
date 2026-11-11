@extends('layouts.admin')
@section('title', 'Dashboard')

@section('content')
@php
    use App\Models\Order;
    $m = $metrics;
    $rp = fn ($v) => 'Rp ' . number_format($v, 0, ',', '.');
    $label = fn ($v) => ucwords(str_replace('-', ' ', (string) $v));
@endphp

<div class="admin-head d-flex flex-wrap justify-content-between align-items-end gap-2">
    <div>
        <h1 class="h3 mb-1 text-uppercase" style="letter-spacing:.04em">Studio Dashboard & Real-Time Metrics</h1>
        <p>{{ now()->locale('id')->translatedFormat('l, d F Y') }}
            · <span class="live-dot" aria-hidden="true"></span>
            <span data-live-status>Live · diperbarui <span data-metric="updated_at">{{ $m['updated_at'] }}</span></span>
        </p>
    </div>
    @if($m['awaiting_verification'])
        <a href="{{ route('admin.orders.index', ['payment_status' => 'proof_uploaded']) }}" class="btn btn-outline-plum btn-sm">
            <span data-metric="awaiting_verification">{{ $m['awaiting_verification'] }}</span> pembayaran perlu diverifikasi
        </a>
    @endif
</div>

{{-- KPI real-time --}}
<div class="row g-3 mb-4" data-dashboard-metrics="{{ route('admin.dashboard.metrics') }}">
    <div class="col-6 col-xl-3">
        <div class="kpi accent">
            <div class="label">Today's Revenue</div>
            <div class="value" data-metric="revenue_today" data-format="rp">{{ $rp($m['revenue_today']) }}</div>
            <div class="note">
                <span data-metric="revenue_change" data-format="change"
                      class="{{ ($m['revenue_change'] ?? 0) < 0 ? 'text-danger' : 'text-success' }}">
                    {{ $m['revenue_change'] === null ? '—' : ($m['revenue_change'] >= 0 ? '+' : '') . $m['revenue_change'] . '% vs kemarin' }}
                </span>
                <div class="text-muted">Total: <span data-metric="revenue_total" data-format="rp">{{ $rp($m['revenue_total']) }}</span></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="kpi">
            <div class="label">Active Orders</div>
            <div class="value"><span data-metric="active_orders">{{ $m['active_orders'] }}</span> Orders</div>
            <div class="note text-muted">
                <span data-metric="preparing">{{ $m['preparing'] }}</span> dirangkai ·
                <span data-metric="out_for_delivery">{{ $m['out_for_delivery'] }}</span> dikirim
            </div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="kpi">
            <div class="label">Smart Wizard Queries</div>
            <div class="value"><span data-metric="wizard_30d">{{ $m['wizard_30d'] }}</span> Queries</div>
            <div class="note text-muted">
                <span data-metric="wizard_today">{{ $m['wizard_today'] }}</span> hari ini ·
                <span data-metric="wizard_conversion" data-format="percent">{{ $m['wizard_conversion'] === null ? '—' : $m['wizard_conversion'] . '%' }}</span> conversion (30 hari)
            </div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="kpi {{ $m['stock_alerts'] ? 'alert-kpi' : '' }}" data-stock-kpi>
            <div class="label">Flower Stock Alert</div>
            <div class="value"><span data-metric="stock_alerts">{{ $m['stock_alerts'] }}</span> Items Low</div>
            <div class="note text-muted"><span data-metric="stock_out">{{ $m['stock_out'] }}</span> habis · bouquet & per tangkai</div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-xl-8">
        {{-- Grafik pendapatan --}}
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h2 class="section-title h6 mb-0">Pendapatan 14 Hari Terakhir</h2>
            <a href="{{ route('admin.reports.sales') }}" class="small">Laporan penjualan →</a>
        </div>
        <div class="panel mb-4">
            @include('admin._bar-chart', ['series' => $revenueSeries, 'label' => 'Pendapatan harian 14 hari terakhir'])
        </div>

        {{-- Pesanan terbaru --}}
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

    {{-- Preferensi Smart Wizard --}}
    <div class="col-xl-4">
        <h2 class="section-title h6 mb-2">Smart Wizard Preferences</h2>
        <div class="panel">
            <div class="small fw-semibold mb-3">Most Requested Occasions <span class="text-muted fw-normal">(30 hari)</span></div>
            @forelse($wizardOccasions as $row)
                <div class="mb-3">
                    <div class="d-flex justify-content-between small mb-1">
                        <span>{{ $label($row['value']) }}</span>
                        <span class="text-muted">{{ $row['percent'] }}%</span>
                    </div>
                    <div class="bar" role="img" aria-label="{{ $label($row['value']) }} {{ $row['percent'] }} persen"><span style="width: {{ $row['percent'] }}%"></span></div>
                </div>
            @empty
                <p class="small text-muted">Belum ada pemakaian wizard.</p>
            @endforelse

            <div class="small fw-semibold mt-4 mb-2">Top Color Palettes</div>
            @forelse($wizardColors as $i => $row)
                <div class="mini-card d-flex justify-content-between small py-2">
                    <span>{{ $i + 1 }}. {{ $label($row['value']) }}</span>
                    <span class="text-muted">{{ $row['total'] }} queries</span>
                </div>
            @empty
                <p class="small text-muted mb-0">—</p>
            @endforelse

            <div class="small fw-semibold mt-4 mb-2">Bouquet Terlaris <span class="text-muted fw-normal">(30 hari)</span></div>
            @forelse($bestSellers as $i => $item)
                <div class="mini-card d-flex justify-content-between small py-2">
                    <span>{{ $i + 1 }}. {{ $item['name'] }}</span>
                    <span class="text-mauve">{{ $item['qty'] }} terjual</span>
                </div>
            @empty
                <p class="small text-muted mb-0">Belum ada penjualan lunas.</p>
            @endforelse
        </div>
    </div>
</div>

{{-- Stok --}}
@if($lowStockCount)
    <div class="d-flex justify-content-between align-items-center mt-4 mb-2">
        <h2 class="section-title h6 mb-0">Peringatan stok ({{ $lowStockCount }} item)</h2>
        <a href="{{ route('admin.bouquets.index', ['stock' => 'low']) }}" class="small">Lihat bouquet</a>
    </div>
    <div class="card">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr><th>Item</th><th>Jenis</th><th>Stok</th><th>Batas minimum</th><th class="text-end">Status / Aksi</th></tr></thead>
                <tbody>
                @foreach($lowStockBouquets as $item)
                    <tr>
                        <td>{{ $item->name }}</td>
                        <td class="small text-muted">Bouquet</td>
                        <td>{{ $item->stock }}</td>
                        <td class="text-muted">{{ $item->low_stock_threshold }}</td>
                        <td class="text-end">
                            <span class="badge-soft {{ $item->stock <= 0 ? 'danger' : 'warning' }} me-2">{{ $item->stock <= 0 ? 'Habis' : 'Sisa ' . $item->stock }}</span>
                            <a href="{{ route('admin.bouquets.edit', $item) }}" class="btn btn-sm btn-outline-plum">Restock</a>
                        </td>
                    </tr>
                @endforeach
                @foreach($lowStockFlowers as $item)
                    <tr>
                        <td>{{ $item->name }}</td>
                        <td class="small text-muted">Per tangkai</td>
                        <td>{{ $item->stock }} tangkai</td>
                        <td class="text-muted">{{ $item->reorder_level }}</td>
                        <td class="text-end">
                            <span class="badge-soft {{ $item->stock <= 0 ? 'danger' : 'warning' }}">{{ $item->stock <= 0 ? 'Habis' : 'Restock' }}</span>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif
@endsection

@push('scripts')
<script type="module">
// Real-time: perbarui KPI setiap 30 detik (berhenti saat tab tidak aktif)
(() => {
    const box = document.querySelector('[data-dashboard-metrics]');
    if (!box) return;

    const rp = new Intl.NumberFormat('id-ID');
    const fmt = {
        rp: (v) => 'Rp ' + rp.format(Math.round(v)),
        change: (v) => v === null ? '—' : `${v >= 0 ? '+' : ''}${v}% vs kemarin`,
        percent: (v) => v === null ? '—' : `${v}%`,
    };
    const status = document.querySelector('[data-live-status]');

    async function refresh() {
        if (document.hidden) return;
        try {
            const res = await fetch(box.dataset.dashboardMetrics, { headers: { Accept: 'application/json' } });
            if (!res.ok) throw new Error(res.status);
            const data = await res.json();

            document.querySelectorAll('[data-metric]').forEach((el) => {
                const key = el.dataset.metric;
                if (!(key in data)) return;
                el.textContent = el.dataset.format ? fmt[el.dataset.format](data[key]) : data[key];
            });
            const change = document.querySelector('[data-metric="revenue_change"]');
            change?.classList.toggle('text-danger', (data.revenue_change ?? 0) < 0);
            change?.classList.toggle('text-success', (data.revenue_change ?? 0) >= 0);
            document.querySelector('[data-stock-kpi]')?.classList.toggle('alert-kpi', data.stock_alerts > 0);
            status.classList.remove('text-danger');
        } catch (e) {
            status.classList.add('text-danger');
        }
    }

    setInterval(refresh, 30000);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) refresh(); });
})();
</script>
@endpush
