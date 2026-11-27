@extends('layouts.admin')
@section('title', $flower->exists ? 'Edit Bunga' : 'Tambah Bunga')

@section('content')
@php $v = fn ($k, $d = null) => old($k, $flower->{$k} ?? $d); $num = fn ($x) => $x === null || $x === '' ? '' : (int) $x; @endphp
<nav class="mb-3 small"><a href="{{ route('admin.inventory.index') }}" class="text-decoration-none text-mauve">&larr; Inventory Hub</a></nav>
<div class="admin-head">
    <h1 class="h3 mb-1">{{ $flower->exists ? 'Edit ' . $flower->name : 'Tambah Bunga per Tangkai' }}</h1>
    <p>Bunga aktif tampil di katalog <em>Per Tangkai</em>. HPP hanya terlihat oleh admin.</p>
</div>

<form method="POST" action="{{ $flower->exists ? route('admin.inventory.flowers.update', $flower) : route('admin.inventory.flowers.store') }}" class="panel" style="max-width: 860px">
    @csrf @if($flower->exists) @method('PUT') @endif
    <div class="row g-3">
        @foreach([
            ['name', 'Nama varietas', 'col-md-6', 'text', 'mis. Rose Pink Ecuador'],
            ['species', 'Jenis bunga', 'col-md-3', 'text', 'rose, lily, tulip…'],
            ['color', 'Warna', 'col-md-3', 'text', 'pink'],
            ['origin', 'Asal / pemasok', 'col-md-6', 'text', 'Lokal (Bandung) / Import (Holland)'],
        ] as [$k, $label, $col, $type, $ph])
            <div class="{{ $col }}">
                <label for="{{ $k }}" class="form-label small mb-1">{{ $label }}</label>
                <input id="{{ $k }}" name="{{ $k }}" type="{{ $type }}" value="{{ $v($k) }}" placeholder="{{ $ph }}" class="form-control @error($k) is-invalid @enderror">
                @error($k) <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
        @endforeach
        <div class="col-md-3">
            <label for="shelf_life_days" class="form-label small mb-1">Umur segar (hari)</label>
            <input id="shelf_life_days" name="shelf_life_days" type="number" min="1" max="30" value="{{ $v('shelf_life_days') }}"
                   placeholder="bawaan {{ config('flore.inventory.shelf_life_days') }}" class="form-control @error('shelf_life_days') is-invalid @enderror">
            @error('shelf_life_days') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="col-md-3">
            <label for="reorder_level" class="form-label small mb-1">Batas restock (tangkai)</label>
            <input id="reorder_level" name="reorder_level" type="number" min="0" required value="{{ $v('reorder_level') }}" class="form-control @error('reorder_level') is-invalid @enderror">
            @error('reorder_level') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>

        @foreach([
            ['price_per_stem', 'Harga jual / tangkai (Rp)', true],
            ['cost_per_stem', 'HPP / tangkai (Rp)', false],
            ['bundle_size', 'Isi paket (tangkai)', false],
            ['bundle_price', 'Harga paket (Rp)', false],
        ] as [$k, $label, $req])
            <div class="col-md-3">
                <label for="{{ $k }}" class="form-label small mb-1">{{ $label }}</label>
                <input id="{{ $k }}" name="{{ $k }}" type="number" min="0" step="1" value="{{ $num($v($k)) }}" @if($req) required @endif class="form-control @error($k) is-invalid @enderror">
                @error($k) <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
        @endforeach

        @unless($flower->exists)
            <div class="col-md-3">
                <label for="stock" class="form-label small mb-1">Stok awal (tangkai)</label>
                <input id="stock" name="stock" type="number" min="0" required value="{{ old('stock', 0) }}" class="form-control @error('stock') is-invalid @enderror">
                @error('stock') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
        @else
            <div class="col-12 small text-muted">Stok saat ini {{ $flower->stock }} tangkai — ubah lewat tombol <em>Reorder</em> di Inventory Hub agar tercatat di riwayat.</div>
        @endunless

        <div class="col-12">
            <label for="description" class="form-label small mb-1">Deskripsi</label>
            <textarea id="description" name="description" rows="2" class="form-control @error('description') is-invalid @enderror">{{ $v('description') }}</textarea>
        </div>
        <div class="col-12 form-check ms-2">
            <input type="hidden" name="is_active" value="0">
            <input id="is_active" type="checkbox" name="is_active" value="1" class="form-check-input" @checked(old('is_active', $flower->is_active))>
            <label for="is_active" class="form-check-label small">Aktif (dijual di katalog per tangkai)</label>
        </div>
    </div>
    <div class="mt-3 d-flex gap-2">
        <button class="btn btn-plum">Simpan</button>
        <a href="{{ route('admin.inventory.index') }}" class="btn btn-link">Batal</a>
    </div>
</form>
@endsection
