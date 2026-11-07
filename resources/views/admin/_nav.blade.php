<nav class="mb-3 d-flex flex-wrap gap-2 align-items-center" aria-label="Navigasi admin">
    <a href="{{ route('admin.dashboard') }}" class="btn btn-sm btn-outline-secondary">&larr; Dashboard</a>
    <span class="vr d-none d-sm-block"></span>
    <a href="{{ route('admin.categories.index') }}"
       class="btn btn-sm {{ request()->routeIs('admin.categories.*') ? 'btn-primary' : 'btn-outline-primary' }}">Kategori</a>
    <a href="{{ route('admin.bouquets.index') }}"
       class="btn btn-sm {{ request()->routeIs('admin.bouquets.*') ? 'btn-primary' : 'btn-outline-primary' }}">Bouquet</a>
    <a href="{{ route('admin.bouquet-images.index') }}"
       class="btn btn-sm {{ request()->routeIs('admin.bouquet-images.*') ? 'btn-primary' : 'btn-outline-primary' }}">Gambar Bouquet</a>
</nav>