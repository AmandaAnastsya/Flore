@extends('layouts.admin')
@section('title', 'Detail Customer')

@section('content')
@php
    $rp = fn ($v) => 'Rp ' . number_format((float) $v, 0, ',', '.');
    $types = \App\Models\Reminder::TYPE_LABELS;
@endphp

<nav class="mb-3 small"><a href="{{ route('admin.reports.customers') }}" class="text-decoration-none text-mauve">&larr; Data Customer</a></nav>

<div class="admin-head d-flex flex-wrap justify-content-between align-items-end gap-2">
    <div>
        <h1 class="h3 mb-1">{{ $customer->name }}</h1>
        <p>Customer sejak {{ $customer->created_at->locale('id')->translatedFormat('d F Y') }}</p>
    </div>
    <span class="badge-soft {{ $customer->reminders_enabled ? 'success' : 'secondary' }}">
        Pengingat email {{ $customer->reminders_enabled ? 'aktif (H-' . $customer->reminder_days . ')' : 'nonaktif' }}
    </span>
</div>

<div class="row g-3 mb-4">
    <div class="col-6 col-xl-3"><div class="kpi accent"><div class="label">Total Belanja</div><div class="value">{{ $rp($stats['spent']) }}</div><div class="note text-muted">pesanan lunas</div></div></div>
    <div class="col-6 col-xl-3"><div class="kpi"><div class="label">Jumlah Pesanan</div><div class="value">{{ $stats['orders'] }}</div></div></div>
    <div class="col-6 col-xl-3"><div class="kpi"><div class="label">Terkirim</div><div class="value">{{ $stats['delivered'] }}</div></div></div>
    <div class="col-6 col-xl-3"><div class="kpi"><div class="label">Dibatalkan</div><div class="value">{{ $stats['cancelled'] }}</div></div></div>
</div>

<div class="row g-4">
    <div class="col-xl-4">
        <h2 class="section-title h6 mb-2">Profil & Kontak</h2>
        <div class="panel mb-4">
            <dl class="row small mb-0">
                <dt class="col-5">Email</dt><dd class="col-7 text-break">{{ $customer->email }}</dd>
                <dt class="col-5">No. HP</dt><dd class="col-7">{{ $customer->phone ?? '—' }}</dd>
                <dt class="col-5">Ulang tahun</dt><dd class="col-7">{{ $customer->date_of_birth?->locale('id')->translatedFormat('d F Y') ?? '—' }}</dd>
                <dt class="col-5">Anniversary</dt><dd class="col-7 mb-0">{{ $customer->anniversary_date?->locale('id')->translatedFormat('d F Y') ?? '—' }}</dd>
            </dl>
        </div>

        <h2 class="section-title h6 mb-2">Momen Mendatang</h2>
        <div class="panel mb-4">
            @forelse($upcoming as $m)
                <div class="mini-card small">
                    <div class="fw-semibold">{{ $types[$m['type']] ?? 'Momen' }}: {{ $m['title'] }}</div>
                    <div class="text-muted">{{ $m['occasion_date']->locale('id')->translatedFormat('d F Y') }} · {{ $m['days_left'] }} hari lagi</div>
                </div>
            @empty
                <p class="small text-muted mb-0">Belum ada tanggal penting tersimpan.</p>
            @endforelse
        </div>

        @if($testimonials->isNotEmpty())
            <h2 class="section-title h6 mb-2">Testimonial</h2>
            <div class="panel">
                @foreach($testimonials as $t)
                    <div class="mini-card small">
                        <div class="stars">{{ str_repeat('★', $t->rating) }}<span class="off">{{ str_repeat('★', 5 - $t->rating) }}</span></div>
                        “{{ Str::limit($t->content, 120) }}”
                        <div class="text-muted mt-1">{{ \App\Models\Testimonial::STATUS_LABELS[$t->status()] }}</div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <div class="col-xl-8">
        <h2 class="section-title h6 mb-2">Riwayat Pesanan</h2>
        <div class="card">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead><tr><th>No. Pesanan</th><th>Tanggal</th><th>Barang</th><th class="text-end">Total</th><th>Status</th></tr></thead>
                    <tbody>
                    @forelse($orders as $o)
                        <tr>
                            <td><a href="{{ route('admin.orders.show', $o) }}" class="fw-semibold text-decoration-none">{{ $o->order_number }}</a></td>
                            <td class="small">{{ $o->created_at->format('d M Y') }}</td>
                            <td class="small">{{ Str::limit($o->items->map(fn ($i) => $i->bouquet_name ?? $i->flower_name)->filter()->join(', '), 40) }}</td>
                            <td class="text-end">{{ $rp($o->total_amount) }}</td>
                            <td><span class="badge-soft {{ $statusBadges[$o->status] ?? 'secondary' }}">{{ $statusLabels[$o->status] ?? $o->status }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted py-4">Customer ini belum pernah memesan.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="mt-3">{{ $orders->links() }}</div>
    </div>
</div>
@endsection
