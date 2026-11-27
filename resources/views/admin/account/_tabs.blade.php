<nav class="nav report-tabs mb-4" aria-label="Menu akun admin">
    @foreach([
        'admin.account.profile' => 'Profil Saya',
        'admin.account.settings' => 'Pengaturan Akun',
        'admin.password.edit' => 'Ganti Password',
    ] as $route => $label)
        <a href="{{ route($route) }}" class="nav-link {{ request()->routeIs($route) ? 'active' : '' }}">{{ $label }}</a>
    @endforeach
</nav>
