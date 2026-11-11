@extends('layouts.admin')
@section('title', 'Customer Data')

@section('content')
@php $rp = fn ($v) => 'Rp ' . number_format((float) $v, 0, ',', '.'); @endphp

@include('admin.reports._header', ['title' => 'Customer Data & Report', 'subtitle' => 'Data customer, riwayat belanja, dan tanggal penting untuk Smart Reminder.'])

<div class="row g-3 mb-4">
    <div class="col-6 col-xl-3"><div class="kpi accent"><div class="label">Total Customer</div><div class="value">{{ $stats['total'] }}</div></div></div>
    <div class="col-6 col-xl-3"><div class="kpi"><div class="label">Baru (30 hari)</div><div class="value">{{ $stats['new_30d'] }}</div></div></div>
    <div class="col-6 col-xl-3"><div class="kpi"><div class="label">Pernah Belanja</div><div class="value">{{ $stats['buyers'] }}</div></div></div>
    <div class="col-6 col-xl-3"><div class="kpi"><div class="label">Repeat Customer</div><div class="value">{{ $stats['repeat'] }}</div><div class="note text-muted">≥ 2 pesanan</div></div></div>
</div>

<form method="GET" class="panel py-3 mb-3 d-flex flex-wrap gap-2 align-items-end">
    <div class="flex-grow-1">
        <label for="q" class="form-label mb-0">Cari</label>
        <input id="q" name="q" value="{{ $q }}" maxlength="100" class="form-control form-control-sm" placeholder="Nama, email, atau no. HP">
    </div>
    <div>
        <label for="sort" class="form-label mb-0">Urutkan</label>
        <select id="sort" name="sort" class="form-select form-select-sm">
            <option value="spent" @selected($sort === 'spent')>Total belanja terbesar</option>
            <option value="orders" @selected($sort === 'orders')>Pesanan terbanyak</option>
            <option value="newest" @selected($sort === 'newest')>Terbaru mendaftar</option>
        </select>
    </div>
    <button class="btn btn-sm btn-plum">Terapkan</button>
    <a href="{{ request()->fullUrlWithQuery(['export' => 'csv', 'page' => null]) }}" class="btn btn-sm btn-outline-secondary">Ekspor CSV</a>
</form>

<div class="card">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr><th>Customer</th><th>Kontak</th><th>Tanggal penting</th><th class="text-end">Pesanan</th><th class="text-end">Total belanja</th><th>Pesanan terakhir</th></tr>
            </thead>
            <tbody>
            @forelse($customers as $c)
                <tr>
                    <td>
                        <div class="fw-semibold">{{ $c->name }}</div>
                        <div class="small text-muted">Terdaftar {{ $c->created_at->format('d M Y') }}</div>
                    </td>
                    <td class="small">{{ $c->email }}<div class="text-muted">{{ $c->phone ?? '—' }}</div></td>
                    <td class="small">
                        @if($c->date_of_birth) <div>🎂 {{ $c->date_of_birth->locale('id')->translatedFormat('d F') }}</div> @endif
                        @if($c->anniversary_date) <div>💞 {{ $c->anniversary_date->locale('id')->translatedFormat('d F') }}</div> @endif
                        @if(! $c->date_of_birth && ! $c->anniversary_date) <span class="text-muted">—</span> @endif
                    </td>
                    <td class="text-end">{{ $c->orders_count }}</td>
                    <td class="text-end fw-semibold">{{ $rp($c->total_spent) }}</td>
                    <td class="small">{{ $c->last_order_at ? \Illuminate\Support\Carbon::parse($c->last_order_at)->format('d M Y') : '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center text-muted py-4">Tidak ada customer yang cocok.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $customers->links() }}</div>
@endsection
