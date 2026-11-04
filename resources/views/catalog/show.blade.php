@extends('layouts.app')

@section('content')
@include('catalog._styles')

<div class="container py-4 catalog">

    <nav aria-label="breadcrumb">
        <ol class="breadcrumb small">
            <li class="breadcrumb-item"><a href="{{ route('catalog.index') }}">Katalog</a></li>
            <li class="breadcrumb-item">
                <a href="{{ route('catalog.index', ['category' => $bouquet->category_id]) }}">{{ $bouquet->category->name }}</a>
            </li>
            <li class="breadcrumb-item active" aria-current="page">{{ $bouquet->name }}</li>
        </ol>
    </nav>

    <div class="row g-4">
        <div class="col-md-6">
            <div class="position-relative">
                <img src="{{ $bouquet->image_url }}" alt="{{ $bouquet->name }}" class="detail-img rounded">
                <div class="tags">
                    @if($bouquet->stock_status === 'out') <span class="tag tag-out">Habis</span> @endif
                    @if($bouquet->is_same_day) <span class="tag tag-sameday">Same Day</span> @endif
                    @if($bouquet->style === 'luxury') <span class="tag tag-luxury">Luxury</span> @endif
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <h1 class="h3 serif mb-1">{{ $bouquet->name }}</h1>
            <div class="price fs-4 mb-3">Rp {{ number_format($bouquet->price, 0, ',', '.') }}</div>

            @if($bouquet->description)
                <p class="text-muted">{{ $bouquet->description }}</p>
            @endif

            <dl class="row small mb-3">
                <dt class="col-5 col-sm-4">Kategori</dt><dd class="col-7 col-sm-8">{{ $bouquet->category->name }}</dd>
                @foreach(['occasion' => 'Occasion', 'color' => 'Warna', 'style' => 'Style', 'size' => 'Ukuran'] as $field => $label)
                    @if($bouquet->$field)
                        <dt class="col-5 col-sm-4">{{ $label }}</dt>
                        <dd class="col-7 col-sm-8">{{ ucfirst(str_replace('-', ' ', $bouquet->$field)) }}</dd>
                    @endif
                @endforeach
                <dt class="col-5 col-sm-4">Pengiriman</dt>
                <dd class="col-7 col-sm-8">{{ $bouquet->is_same_day ? 'Same day delivery tersedia' : 'Reguler' }}</dd>
            </dl>

            <div class="mb-3">
                @if($bouquet->stock_status === 'out')
                    <span class="badge bg-danger">Stok habis</span>
                @elseif($bouquet->stock_status === 'low')
                    <span class="badge bg-warning text-dark">Sisa {{ $bouquet->stock }}</span>
                @else
                    <span class="badge bg-success">Tersedia</span>
                @endif
            </div>

            <div class="d-grid d-sm-flex gap-2">
                @if($bouquet->stock_status === 'out')
                    <button class="btn btn-outline-secondary" disabled>Stok habis</button>
                @else
                    {{-- diaktifkan di item Keranjang Belanja --}}
                    <button class="btn btn-rose px-4" disabled title="Keranjang segera hadir">Add to cart</button>
                @endif
                <a href="{{ $back }}" class="btn btn-outline-secondary">Kembali ke katalog</a>
            </div>
        </div>
    </div>

    @if($related->isNotEmpty())
        <h2 class="h5 section-title mt-5 mb-3">Mungkin kamu suka</h2>
        <div class="row g-4">
            @foreach($related as $bouquet)
                <div class="col-12 col-sm-6 col-lg-3">
                    @include('catalog._card')
                </div>
            @endforeach
        </div>
    @endif
</div>
@endsection