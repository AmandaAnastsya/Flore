@extends('layouts.app')

@section('content')
<style>
    .catalog { --rose: #8e5a66; --rose-dark: #6f4350; --blush: #f5e9ec; }
    .catalog .hero { background: var(--blush); border: 1px solid #e8c6cf; border-radius: .5rem; padding: 1.75rem; }
    .catalog .hero h1, .catalog .section-title { font-family: Georgia, 'Times New Roman', serif; color: var(--rose); letter-spacing: .04em; text-transform: uppercase; }
    .catalog .hero h1 { font-size: 1.6rem; }
    .catalog .btn-rose { background: var(--rose); color: #fff; border: 0; }
    .catalog .btn-rose:hover { background: var(--rose-dark); color: #fff; }
    .catalog .product { background: #fff; border: 1px solid #f0dde2; border-radius: .5rem; overflow: hidden; height: 100%; display: flex; flex-direction: column; }
    .catalog .product img { width: 100%; aspect-ratio: 4 / 3; object-fit: cover; background: var(--blush); }
    .catalog .tags { position: absolute; top: .6rem; left: .6rem; display: flex; gap: .3rem; flex-wrap: wrap; }
    .catalog .tag { font-size: .68rem; letter-spacing: .05em; text-transform: uppercase; color: #fff; padding: .2rem .5rem; border-radius: .2rem; }
    .catalog .tag-sameday { background: var(--rose); }
    .catalog .tag-luxury { background: #a27a84; }
    .catalog .tag-out { background: #6c757d; }
    .catalog .price { color: var(--rose); font-size: 1.15rem; }
</style>

<div class="container py-4 catalog">

    <div class="hero mb-4 d-md-flex justify-content-between align-items-center">
        <div>
            <h1 class="mb-1">Craft Your Moment With Floré</h1>
            <p class="text-muted mb-3 mb-md-0">Koleksi bouquet lembut dan elegan. Pilih sesuai momen, gaya, dan budget.</p>
        </div>
        @if(Route::has('wizard.index'))
            <a href="{{ route('wizard.index') }}" class="btn btn-rose">Buka Smart Wizard</a>
        @endif
    </div>

    <form method="GET" action="{{ route('catalog.index') }}" class="card card-body border-0 shadow-sm mb-4">
        <div class="row g-2">
            <div class="col-12 col-lg-4">
                <div class="input-group">
                    <input type="text" name="q" value="{{ $filters['q'] }}" maxlength="100"
                           class="form-control" placeholder="Cari bouquet atau jenis bunga...">
                    <button class="btn btn-rose">Cari</button>
                </div>
            </div>

            @foreach($options as $name => [$placeholder, $choices])
                <div class="col-6 col-md-4 col-lg">
                    <select name="{{ $name }}" class="form-select" aria-label="{{ $placeholder }}" onchange="this.form.submit()">
                        <option value="">{{ $placeholder }}</option>
                        @foreach($choices as $value => $text)
                            <option value="{{ $value }}" @selected((string) $filters[$name] === (string) $value)>{{ $text }}</option>
                        @endforeach
                    </select>
                </div>
            @endforeach

            <div class="col-6 col-md-4 col-lg">
                <select name="sort" class="form-select" aria-label="Urutkan" onchange="this.form.submit()">
                    @foreach($sorts as $value => $text)
                        <option value="{{ $value }}" @selected($filters['sort'] === $value)>Urut: {{ $text }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        @if(request()->hasAny(['q', 'category', 'occasion', 'style', 'color', 'price', 'sort']))
            <div class="mt-2"><a href="{{ route('catalog.index') }}" class="small">Reset semua filter</a></div>
        @endif
    </form>

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="h5 section-title mb-0">The Elegance Collection</h2>
        <span class="text-muted small">
            @if($bouquets->total())
                Menampilkan {{ $bouquets->firstItem() }} - {{ $bouquets->lastItem() }} dari {{ $bouquets->total() }} bouquet
            @else
                Tidak ada hasil
            @endif
        </span>
    </div>

    <div class="row g-4">
        @forelse($bouquets as $bouquet)
            <div class="col-12 col-sm-6 col-lg-4">
                <div class="product">
                    <div class="position-relative">
                        <img src="{{ $bouquet->image_url }}" alt="{{ $bouquet->name }}" loading="lazy">
                        <div class="tags">
                            @if($bouquet->stock_status === 'out') <span class="tag tag-out">Habis</span> @endif
                            @if($bouquet->is_same_day) <span class="tag tag-sameday">Same Day</span> @endif
                            @if($bouquet->style === 'luxury') <span class="tag tag-luxury">Luxury</span> @endif
                        </div>
                    </div>
                    <div class="p-3 d-flex flex-column flex-grow-1">
                        <div class="fw-semibold">{{ $bouquet->name }}</div>
                        <div class="text-muted small mb-2">{{ $bouquet->description }}</div>
                        <div class="price mb-1">Rp {{ number_format($bouquet->price, 0, ',', '.') }}</div>
                        <div class="small mb-3">
                            @if($bouquet->stock_status === 'low')
                                <span class="text-warning-emphasis">Sisa {{ $bouquet->stock }}</span>
                            @elseif($bouquet->stock_status === 'ok' && $bouquet->is_same_day)
                                <span class="text-success">Same day delivery tersedia</span>
                            @endif
                        </div>
                        <div class="mt-auto">
                            @if($bouquet->stock_status === 'out')
                                <button class="btn btn-outline-secondary w-100" disabled>Stok habis</button>
                            @else
                                {{-- tombol aktif dibuat di item Keranjang Belanja --}}
                                <button class="btn btn-rose w-100" disabled title="Keranjang segera hadir">Add to cart</button>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-12 text-center text-muted py-5">
                Tidak ada bouquet yang cocok dengan pencarianmu.
                <a href="{{ route('catalog.index') }}">Lihat semua</a>
            </div>
        @endforelse
    </div>

    <div class="mt-4">{{ $bouquets->links() }}</div>
</div>
@endsection