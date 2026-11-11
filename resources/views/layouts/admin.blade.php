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
    $link = fn (string $pattern) => request()->routeIs($pattern) ? 'active' : '';
@endphp

<header class="admin-topbar">
    <a href="{{ route('admin.dashboard') }}" class="brand-name">Floré Studio</a>
    <span class="portal d-none d-sm-inline">Admin Portal</span>

    <div class="ms-auto d-flex align-items-center gap-3">
        <span class="who d-none d-md-inline">{{ auth()->user()->name }}</span>
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

        <div class="group">Katalog</div>
        <a href="{{ route('admin.bouquets.index') }}" class="{{ $link('admin.bouquets.*') }}">Inventory & Bouquet</a>
        <a href="{{ route('admin.categories.index') }}" class="{{ $link('admin.categories.*') }}">Kategori</a>
        <a href="{{ route('admin.bouquet-images.index') }}" class="{{ $link('admin.bouquet-images.*') }}">Gambar Bouquet</a>
    </nav>

    <main class="admin-main">
        @yield('content')
    </main>
</div>

@stack('scripts')
</body>
</html>
