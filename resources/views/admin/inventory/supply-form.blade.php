@extends('layouts.admin')
@section('title', $supply->exists ? 'Edit Supply' : 'Tambah Supply')

@section('content')
@php use App\Models\Supply; $v = fn ($k) => old($k, $supply->{$k}); @endphp
<nav class="mb-3 small"><a href="{{ route('admin.inventory.index', ['tab' => $supply->type]) }}" class="text-decoration-none text-mauve">&larr; Inventory Hub</a></nav>
<div class="admin-head">
    <h1 class="h3 mb-1">{{ $supply->exists ? 'Edit ' . $supply->name : 'Tambah Perlengkapan' }}</h1>
    <p>Pita, wrapping paper, vas, dan aksesoris pendukung rangkaian.</p>
</div>

<form method="POST" action="{{ $supply->exists ? route('admin.inventory.supplies.update', $supply) : route('admin.inventory.supplies.store') }}" class="panel" style="max-width: 720px">
    @csrf @if($supply->exists) @method('PUT') @endif
    <div class="row g-3">
        <div class="col-md-6">
            <label for="type" class="form-label small mb-1">Kategori</label>
            <select id="type" name="type" class="form-select">
                @foreach(Supply::TYPES as $k => $label) <option value="{{ $k }}" @selected($v('type') === $k)>{{ $label }}</option> @endforeach
            </select>
        </div>
        <div class="col-md-6">
            <label for="name" class="form-label small mb-1">Nama item</label>
            <input id="name" name="name" value="{{ $v('name') }}" required maxlength="100" placeholder="mis. Pita Satin 2,5 cm" class="form-control @error('name') is-invalid @enderror">
            @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="col-md-6">
            <label for="variant" class="form-label small mb-1">Varian / warna</label>
            <input id="variant" name="variant" value="{{ $v('variant') }}" maxlength="100" placeholder="mis. Dusty Pink" class="form-control">
        </div>
        <div class="col-md-3">
            <label for="unit" class="form-label small mb-1">Satuan</label>
            <select id="unit" name="unit" class="form-select">
                @foreach(Supply::UNITS as $u) <option value="{{ $u }}" @selected($v('unit') === $u)>{{ $u }}</option> @endforeach
            </select>
        </div>
        <div class="col-md-3">
            <label for="reorder_level" class="form-label small mb-1">Batas restock</label>
            <input id="reorder_level" name="reorder_level" type="number" min="0" required value="{{ $v('reorder_level') }}" class="form-control @error('reorder_level') is-invalid @enderror">
        </div>
        <div class="col-md-4">
            <label for="cost_per_unit" class="form-label small mb-1">HPP per unit (Rp)</label>
            <input id="cost_per_unit" name="cost_per_unit" type="number" min="0" value="{{ $v('cost_per_unit') === null ? '' : (int) $v('cost_per_unit') }}" class="form-control @error('cost_per_unit') is-invalid @enderror">
        </div>
        @unless($supply->exists)
            <div class="col-md-4">
                <label for="stock" class="form-label small mb-1">Stok awal</label>
                <input id="stock" name="stock" type="number" min="0" required value="{{ old('stock', 0) }}" class="form-control @error('stock') is-invalid @enderror">
            </div>
        @endunless
        <div class="col-12 form-check ms-2">
            <input type="hidden" name="is_active" value="0">
            <input id="is_active" type="checkbox" name="is_active" value="1" class="form-check-input" @checked(old('is_active', $supply->is_active))>
            <label for="is_active" class="form-check-label small">Aktif</label>
        </div>
    </div>
    <div class="mt-3 d-flex gap-2">
        <button class="btn btn-plum">Simpan</button>
        <a href="{{ route('admin.inventory.index', ['tab' => $supply->type]) }}" class="btn btn-link">Batal</a>
    </div>
</form>
@endsection
