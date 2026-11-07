@extends('layouts.app')

@section('content')
@include('catalog._styles')

<div class="container py-4 catalog">
    @include('catalog._flash')

    <div class="hero mb-4">
        <h1 class="mb-1">Bunga Per Tangkai</h1>
        <p class="text-muted mb-0">Pilih jenis bunga favoritmu, beli per tangkai atau ambil paket hemat.</p>
    </div>

    <form method="GET" action="{{ route('stems.index') }}" class="row g-2 align-items-center mb-4">
        <div class="col-12 col-md-5">
            <div class="input-group">
                <input type="text" name="q" value="{{ $q }}" maxlength="100" class="form-control" placeholder="Cari jenis bunga...">
                <button class="btn btn-rose">Cari</button>
            </div>
        </div>
        <div class="col-auto form-check ms-2">
            <input class="form-check-input" type="checkbox" name="in_stock" value="1" id="in_stock"
                   @checked(request()->boolean('in_stock')) onchange="this.form.submit()">
            <label class="form-check-label" for="in_stock">Hanya yang tersedia</label>
        </div>
        @if($q !== '' || request()->has('in_stock'))
            <div class="col-auto"><a href="{{ route('stems.index') }}" class="small">Reset</a></div>
        @endif
    </form>

    <div class="row g-4">
        @forelse($flowers as $flower)
            @php $max = min($flower->stock, \App\Services\Cart::MAX_STEMS); @endphp
            <div class="col-12 col-sm-6 col-lg-3">
                <div class="product">
                    <div class="position-relative">
                        <img src="{{ $flower->image_url }}" alt="{{ $flower->name }}" loading="lazy">
                        <div class="tags">
                            @if($flower->stock_status === 'out') <span class="tag tag-out">Habis</span> @endif
                        </div>
                    </div>
                    <div class="p-3 d-flex flex-column flex-grow-1">
                        <div class="fw-semibold">{{ $flower->name }}</div>
                        <div class="text-muted small mb-2">{{ $flower->origin }}</div>
                        <div class="price mb-1">Rp {{ number_format($flower->price_per_stem, 0, ',', '.') }} <small class="text-muted">/ tangkai</small></div>

                        @if($flower->bundleSavings() > 0)
                            <div class="small text-success mb-1">
                                Paket {{ $flower->bundle_size }} tangkai Rp {{ number_format($flower->bundle_price, 0, ',', '.') }}
                                (hemat Rp {{ number_format($flower->bundleSavings(), 0, ',', '.') }})
                            </div>
                        @endif

                        <div class="small mb-3">
                            @if($flower->stock_status === 'low')
                                <span class="text-warning-emphasis">Sisa {{ $flower->stock }} tangkai</span>
                            @elseif($flower->stock_status === 'ok')
                                <span class="text-muted">Stok tersedia</span>
                            @endif
                        </div>

                        <div class="mt-auto">
                            @if($flower->stock_status === 'out')
                                <button class="btn btn-outline-secondary w-100" disabled>Stok habis</button>
                            @else
                                <form method="POST" action="{{ route('cart.stems.store', $flower) }}" class="d-flex gap-2">
                                    @csrf
                                    <input type="number" name="quantity" value="1" min="1" max="{{ $max }}"
                                           class="form-control" style="width: 84px" aria-label="Jumlah tangkai {{ $flower->name }}">
                                    <button class="btn btn-rose flex-grow-1">Add to cart</button>
                                </form>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-12 text-center text-muted py-5">
                Tidak ada jenis bunga yang cocok. <a href="{{ route('stems.index') }}">Lihat semua</a>
            </div>
        @endforelse
    </div>
</div>
@endsection