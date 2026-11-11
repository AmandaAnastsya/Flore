@extends('layouts.app')
@section('title', 'Akun Saya')

@section('content')
@php
    use App\Models\Order;
    $user = auth()->user();
    $initials = collect(explode(' ', trim($user->name)))->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->join('');
    $rp = fn ($v) => 'Rp ' . number_format($v, 0, ',', '.');
    $reminderTypes = \App\Models\Reminder::TYPE_LABELS;
@endphp

<div class="container">
    @foreach(['status' => 'success', 'success' => 'success', 'error' => 'danger'] as $key => $type)
        @if(session($key))
            <div class="alert alert-{{ $type }}" role="alert">
                {{ session($key) }}
                @if($key === 'success') <a href="{{ route('cart.index') }}" class="alert-link ms-1">Lihat keranjang</a> @endif
            </div>
        @endif
    @endforeach

    {{-- Banner sapaan --}}
    <section class="welcome-banner mb-4 d-md-flex justify-content-between align-items-center gap-4">
        <div class="d-flex align-items-center gap-3 mb-3 mb-md-0">
            <div class="avatar" aria-hidden="true">{{ $initials }}</div>
            <div>
                <h1 class="mb-1">Halo, {{ $user->name }}!</h1>
                <div class="small" style="color: rgba(255,255,255,.75)">Selamat datang kembali di Floré. Siap merangkai momen berikutnya?</div>
            </div>
        </div>
        <div class="d-flex gap-2 flex-shrink-0">
            <div class="stat"><div class="num">{{ $orderCount }}</div><div class="small">Total pesanan</div></div>
            <div class="stat"><div class="num">{{ $activeCount }}</div><div class="small">Sedang berjalan</div></div>
        </div>
    </section>

    {{-- CTA wizard --}}
    <section class="hero mb-4 d-md-flex justify-content-between align-items-center gap-4">
        <div class="mb-3 mb-md-0">
            <h2 class="h5 section-title mb-1">Craft Your Moment With Floré</h2>
            <p class="text-muted small mb-0">Bingung memilih bouquet? Jawab 5 pertanyaan singkat dan Smart Wizard akan merekomendasikan bouquet yang paling cocok.</p>
        </div>
        <button type="button" class="btn btn-plum px-4 flex-shrink-0" data-bs-toggle="modal" data-bs-target="#wizardModal">
            Buka Smart Wizard ✿
        </button>
    </section>

    <div class="row g-4">
        {{-- Riwayat pesanan --}}
        <div class="col-lg-7">
            <div class="panel">
                <div class="d-flex justify-content-between align-items-center">
                    <h2 class="panel-title">Order History & Invoices</h2>
                    <a href="{{ route('orders.index') }}" class="small mb-3">Lihat semua</a>
                </div>

                @forelse($recentOrders as $order)
                    <div class="mini-card">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div class="fw-semibold">Order {{ $order->order_number }}</div>
                            <div class="small text-muted">{{ $order->created_at->format('d M Y') }}</div>
                        </div>
                        <span class="badge-soft {{ Order::STATUS_BADGES[$order->status] ?? 'secondary' }}">
                            {{ Order::STATUS_LABELS[$order->status] ?? $order->status }}
                        </span>
                        <div class="small mt-2">{{ Str::limit($order->items->map(fn ($i) => $i->bouquet_name ?? $i->flower_name)->filter()->join(', '), 60) }}</div>
                        <div class="small text-muted mb-2">Total: {{ $rp($order->total_amount) }}</div>
                        <a href="{{ route('orders.show', $order->order_number) }}" class="btn btn-sm btn-plum">Lacak Pesanan</a>
                    </div>
                @empty
                    <div class="text-center text-muted small py-4">
                        Kamu belum punya pesanan.
                        <a href="{{ route('catalog.index') }}">Mulai belanja</a>
                    </div>
                @endforelse
            </div>
        </div>

        {{-- Pengingat --}}
        <div class="col-lg-5">
            <div class="panel">
                <h2 class="panel-title mb-1">Smart Calendar & Reminder</h2>
                <p class="small text-muted mb-3">Pengingat tanggal penting & pemesanan otomatis via email.</p>

                @forelse($upcoming as $m)
                    <div class="mini-card">
                        <div class="d-flex justify-content-between gap-2">
                            <div class="fw-semibold small">{{ $reminderTypes[$m['type']] ?? 'Momen' }}: {{ $m['title'] }}</div>
                            <span class="badge-soft primary text-nowrap">{{ $m['days_left'] === 0 ? 'Hari ini' : $m['days_left'] . ' hari lagi' }}</span>
                        </div>
                        <div class="small text-muted">Tanggal: {{ $m['occasion_date']->locale('id')->translatedFormat('d F Y') }}</div>
                    </div>
                @empty
                    <div class="text-center text-muted small py-3">Belum ada momen spesial tersimpan.</div>
                @endforelse

                <a href="{{ route('profile.edit') }}" class="btn btn-outline-plum w-100 mt-3">+ Tambah Reminder Baru</a>
            </div>
        </div>
    </div>
</div>

@include('wizard._modal')
@endsection
