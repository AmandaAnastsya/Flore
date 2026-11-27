{{-- Navigasi akun customer --}}
<nav class="nav report-tabs order-tabs flex-nowrap mb-4" aria-label="Menu akun">
    @foreach([
        'account.profile' => 'Profil Saya',
        'account.addresses.index' => 'Buku Alamat',
        'profile.edit' => 'Momen Spesial & Pengingat',
        'account.settings' => 'Pengaturan Akun',
    ] as $route => $label)
        <a href="{{ route($route) }}" class="nav-link {{ request()->routeIs($route) ? 'active' : '' }}" @if(request()->routeIs($route)) aria-current="page" @endif>{{ $label }}</a>
    @endforeach
</nav>
