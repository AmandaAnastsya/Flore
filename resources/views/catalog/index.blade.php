@extends('layouts.app')

@section('content')
@include('catalog._styles')

<div class="container py-4 catalog">

    <div class="hero mb-4 d-md-flex justify-content-between align-items-center">
        <div>
            <h1 class="mb-1">Craft Your Moment With Floré</h1>
            <p class="text-muted mb-3 mb-md-0">Koleksi bouquet lembut dan elegan. Pilih sesuai momen, gaya, dan budget.</p>
        </div>
        <button type="button" class="btn btn-plum px-4 text-nowrap flex-shrink-0" data-bs-toggle="modal" data-bs-target="#wizardModal">
            Open Smart Wizard ✿
        </button>
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

        <details class="mt-3" @if($advancedActive) open @endif>
            <summary class="fw-semibold">
                Filter lanjutan
                @if($advancedActive) <span class="badge bg-secondary">{{ $advancedActive }}</span> @endif
            </summary>

            <div class="row g-4 mt-1">
                @foreach($groups as $name => [$title, $choices])
                    <div class="col-6 col-md-3">
                        <div class="fw-semibold small mb-1">{{ $title }}</div>
                        @foreach($choices as $value => $text)
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="{{ $name }}[]" value="{{ $value }}"
                                       id="{{ $name }}-{{ $value }}" @checked(in_array($value, $filters[$name], true))>
                                <label class="form-check-label small" for="{{ $name }}-{{ $value }}">{{ $text }}</label>
                            </div>
                        @endforeach
                    </div>
                @endforeach

                <div class="col-12 col-md-3">
                    <div class="fw-semibold small mb-1">Rentang harga (Rp)</div>
                    <div class="input-group input-group-sm mb-3">
                        <input type="number" min="0" name="price_min" value="{{ $filters['price_min'] }}" class="form-control" placeholder="Min">
                        <input type="number" min="0" name="price_max" value="{{ $filters['price_max'] }}" class="form-control" placeholder="Maks">
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="same_day" value="1" id="same_day" @checked($filters['same_day'])>
                        <label class="form-check-label small" for="same_day">Same day delivery</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="in_stock" value="1" id="in_stock" @checked($filters['in_stock'])>
                        <label class="form-check-label small" for="in_stock">Hanya yang tersedia</label>
                    </div>
                </div>
            </div>

            <div class="mt-3"><button class="btn btn-rose btn-sm">Terapkan filter</button></div>
        </details>

        @if(request()->hasAny(['q', 'category', 'style', 'occasion', 'color', 'size', 'price', 'price_min', 'price_max', 'same_day', 'in_stock', 'sort']))
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
                @include('catalog._card')
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

@include('wizard._modal')
@endsection