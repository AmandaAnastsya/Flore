<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@hasSection('title') @yield('title') · @endif Floré Studio — Admin Portal</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=dm-sans:400,500,600|playfair-display:600,700" rel="stylesheet">

    @vite(['resources/sass/app.scss', 'resources/js/app.js'])
</head>
<body class="admin-body">
@php
    $awaiting = \App\Models\Order::awaitingVerification()->count();
    $pendingTestimonials = \App\Models\Testimonial::withStatus('pending')->count();
    $link = fn (string $pattern) => request()->routeIs($pattern) ? 'active' : '';
    $stockCritical = \App\Models\Flower::active()->whereColumn('stock', '<=', 'reorder_level')->count()
        + \App\Models\Supply::active()->whereColumn('stock', '<=', 'reorder_level')->count();
    // pesanan siap kirim tanpa kurir + pengiriman yang melewati ETA
    $dispatchAlerts = \App\Models\Order::where(fn ($q) => $q
        ->where(fn ($w) => $w->where('status', 'preparing')->whereNull('courier_id'))
        ->orWhere(fn ($w) => $w->where('status', 'out_for_delivery')->where('eta_at', '<', now()))
    )->count();
@endphp

<header class="admin-topbar">
    <a href="{{ route('admin.dashboard') }}" class="brand-name">Floré Studio</a>
    <span class="portal d-none d-sm-inline">Admin Portal</span>

    <div class="ms-auto d-flex align-items-center gap-3">
        <a href="{{ route('admin.account.profile') }}" class="who d-none d-md-inline-flex align-items-center gap-2 text-decoration-none" title="Profil saya">
            <span class="avatar-xs" aria-hidden="true">@if(auth()->user()->avatarUrl())<img src="{{ auth()->user()->avatarUrl() }}" alt="">@else{{ auth()->user()->initials() }}@endif</span>
            {{ auth()->user()->name }}
        </a>
        <a href="{{ url('/') }}" class="d-none d-sm-inline">Lihat Toko</a>
        <form method="POST" action="{{ route('logout') }}" class="m-0">
            @csrf
            <button class="btn btn-sm btn-outline-light">Logout</button>
        </form>
    </div>
</header>

<div class="admin-shell">
    <nav class="admin-sidebar" aria-label="Menu admin">
        <div class="group">Overview</div>
        <a href="{{ route('admin.dashboard') }}" class="{{ $link('admin.dashboard') }}">Dashboard Overview</a>

        <div class="group">Operasional</div>
        <a href="{{ route('admin.orders.index') }}" class="{{ $link('admin.orders.*') }}">
            Order Management @if($awaiting) <span class="count">{{ $awaiting }}</span> @endif
        </a>

        <a href="{{ route('admin.florists.index') }}" class="{{ $link('admin.florists.*') }}">Florist & Produksi</a>
        <a href="{{ route('admin.logistics.index') }}" class="{{ $link('admin.logistics.*') }} {{ $link('admin.couriers.*') }}">
            Logistik & Kurir @if($dispatchAlerts) <span class="count" title="Menunggu kurir / melewati ETA">{{ $dispatchAlerts }}</span> @endif
        </a>
        <a href="{{ route('admin.wizard.index') }}" class="{{ $link('admin.wizard.*') }}">Smart Wizard & AI</a>
        <a href="{{ route('admin.reminders.index') }}" class="{{ $link('admin.reminders.*') }}">Smart Reminder</a>

        <div class="group">Katalog & Inventaris</div>
        <a href="{{ route('admin.bouquets.index') }}" class="{{ $link('admin.bouquets.*') }}">Inventory & Bouquet</a>
        <a href="{{ route('admin.inventory.index') }}" class="{{ $link('admin.inventory.*') }}">
            Bunga & Supplies @if($stockCritical) <span class="count" title="Perlu restock">{{ $stockCritical }}</span> @endif
        </a>
        <a href="{{ route('admin.categories.index') }}" class="{{ $link('admin.categories.*') }}">Kategori</a>
        <a href="{{ route('admin.bouquet-images.index') }}" class="{{ $link('admin.bouquet-images.*') }}">Gambar Bouquet</a>

        <div class="group">Laporan</div>
        <a href="{{ route('admin.reports.sales') }}" class="{{ $link('admin.reports.sales') }}">Sales Report</a>
        <a href="{{ route('admin.reports.products') }}" class="{{ $link('admin.reports.products') }}">Produk Terlaris</a>
        <a href="{{ route('admin.reports.inventory') }}" class="{{ $link('admin.reports.inventory') }}">Inventory Report</a>
        <a href="{{ route('admin.reports.expenses') }}" class="{{ $link('admin.reports.expenses*') }}">Biaya Operasional</a>
        <a href="{{ route('admin.reports.customers') }}" class="{{ $link('admin.reports.customers') }}">Data Customer</a>
        <a href="{{ route('admin.testimonials.index') }}" class="{{ $link('admin.testimonials.*') }}">
            Testimonial @if($pendingTestimonials) <span class="count">{{ $pendingTestimonials }}</span> @endif
        </a>

        <div class="group">Pengaturan</div>
        <a href="{{ route('admin.settings.edit') }}" class="{{ $link('admin.settings.*') }}">Studio Settings</a>
        <a href="{{ route('admin.account.profile') }}" class="{{ $link('admin.account.profile') }}">Profil Saya</a>
        <a href="{{ route('admin.account.settings') }}" class="{{ $link('admin.account.settings') }}">Pengaturan Akun</a>
        <a href="{{ route('admin.password.edit') }}" class="{{ $link('admin.password.*') }}">Ganti Password</a>
    </nav>

    <main class="admin-main">
        @yield('content')
    </main>
</div>

@stack('scripts')
</body>
</html>
