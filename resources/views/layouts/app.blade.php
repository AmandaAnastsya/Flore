<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@hasSection('title') @yield('title') · @endif Floré — Soft Elegance Floral Studio</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=dm-sans:400,500,600|playfair-display:600,700" rel="stylesheet">

    @vite(['resources/sass/app.scss', 'resources/js/app.js'])
</head>
<body>
<div id="app" class="d-flex flex-column min-vh-100">

    <div class="announce-bar">Same day delivery tersedia &nbsp;|&nbsp; Gratis kartu ucapan elegan di setiap bouquet</div>

    <nav class="navbar navbar-expand-lg navbar-dark site-header py-3">
        <div class="container">
            <a class="navbar-brand" href="{{ url('/') }}">
                <span class="brand-name">Floré</span>
                <span class="brand-tag">Soft Elegance Floral Studio</span>
            </a>

            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav"
                    aria-controls="mainNav" aria-expanded="false" aria-label="Buka navigasi">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="mainNav">
                <ul class="navbar-nav mx-auto">
                    <li class="nav-item">
                        <a class="nav-link {{ request()->is('/') || request()->routeIs('home') ? 'active' : '' }}"
                           href="{{ auth()->check() ? route('home') : url('/') }}">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('catalog.*') ? 'active' : '' }}" href="{{ route('catalog.index') }}">Katalog</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('stems.*') ? 'active' : '' }}" href="{{ route('stems.index') }}">Per Tangkai</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('wizard.index') }}">Smart Wizard</a>
                    </li>
                    @auth
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('orders.*') ? 'active' : '' }}" href="{{ route('orders.index') }}">Pesanan Saya</a>
                        </li>
                        @if(auth()->user()->isAdmin())
                            <li class="nav-item">
                                <a class="nav-link" href="{{ route('admin.dashboard') }}">Dashboard Admin</a>
                            </li>
                        @endif
                    @endauth
                </ul>

                <ul class="navbar-nav align-items-lg-center">
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('cart.*') ? 'active' : '' }}" href="{{ route('cart.index') }}">
                            Keranjang @if($cartCount) <span class="badge bg-secondary">{{ $cartCount }}</span> @endif
                        </a>
                    </li>
                    @guest
                        @if (Route::has('login'))
                            <li class="nav-item"><a class="nav-link" href="{{ route('login') }}">Login</a></li>
                        @endif
                        @if (Route::has('register'))
                            <li class="nav-item"><a class="nav-link" href="{{ route('register') }}">Register</a></li>
                        @endif
                    @else
                        <li class="nav-item dropdown">
                            <a id="userMenu" class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                {{ Str::limit(Auth::user()->name, 16) }}
                            </a>
                            <div class="dropdown-menu dropdown-menu-end" aria-labelledby="userMenu">
                                <a class="dropdown-item" href="{{ route('home') }}">Akun Saya</a>
                                <a class="dropdown-item" href="{{ route('profile.edit') }}">Profil & Momen Spesial</a>
                                <a class="dropdown-item" href="{{ route('orders.index') }}">Pesanan Saya</a>
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item" href="{{ route('logout') }}"
                                   onclick="event.preventDefault(); document.getElementById('logout-form').submit();">Logout</a>
                                <form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">@csrf</form>
                            </div>
                        </li>
                    @endguest
                </ul>
            </div>
        </div>
    </nav>

    <main class="py-4 flex-grow-1">
        @yield('content')
    </main>

    <footer class="site-footer">
        <div class="container py-5">
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="brand-name mb-2">Floré</div>
                    <p class="mb-0">Rangkaian bunga lembut dan elegan untuk setiap momen spesialmu.</p>
                </div>
                <div class="col-6 col-md-4">
                    <h5 class="mb-3">Client Care</h5>
                    <ul class="list-unstyled mb-0">
                        <li class="mb-1"><a href="{{ route('catalog.index') }}">Katalog Bouquet</a></li>
                        <li class="mb-1"><a href="{{ route('wizard.index') }}">Smart Recommendation Wizard</a></li>
                        <li class="mb-1"><a href="{{ route('orders.index') }}">Lacak Pesanan</a></li>
                    </ul>
                </div>
                <div class="col-6 col-md-4">
                    <h5 class="mb-3">Payment & Logistics</h5>
                    <p class="mb-1">Transfer bank {{ config('flore.payment.bank', 'BCA') }}</p>
                    <p class="mb-0">Pengiriman pagi, siang, dan sore</p>
                </div>
            </div>
        </div>
        <div class="copyright">© {{ date('Y') }} Floré Studio — Soft Elegance Floral Studio</div>
    </footer>
</div>
@stack('scripts')
</body>
</html>
