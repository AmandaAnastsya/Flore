@extends('layouts.admin')

@section('content')
<div class="container-fluid px-0">
    @include('admin._nav')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">Inventaris Bouquet</h1>
        <a href="{{ route('admin.bouquets.create') }}" class="btn btn-primary">+ Tambah Bouquet</a>
    </div>

    @if(session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
    @if($errors->has('stock')) <div class="alert alert-danger">{{ $errors->first('stock') }}</div> @endif

    <form method="GET" class="row g-2 mb-3">
        <div class="col-md-4">
            <input type="text" name="q" value="{{ request('q') }}" class="form-control" placeholder="Cari nama bouquet...">
        </div>
        <div class="col-md-3">
            <select name="category" class="form-select">
                <option value="">Semua kategori</option>
                @foreach($categories as $category)
                    <option value="{{ $category->id }}" @selected(request('category') == $category->id)>{{ $category->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3">
            <select name="stock" class="form-select">
                <option value="">Semua stok</option>
                <option value="low" @selected(request('stock') === 'low')>Stok menipis</option>
                <option value="out" @selected(request('stock') === 'out')>Stok habis</option>
            </select>
        </div>
        <div class="col-md-2 d-grid"><button class="btn btn-outline-primary">Filter</button></div>
    </form>

    <div class="card">
        <div class="table-responsive">
        <table class="table mb-0 align-middle">
            <thead>
                <tr><th></th><th>Nama</th><th>Kategori</th><th>Harga</th><th>Stok</th><th>Ubah stok</th><th class="text-end">Aksi</th></tr>
            </thead>
            <tbody>
            @forelse($bouquets as $bouquet)
                @php
                    $badge = ['ok' => ['success', 'Aman'], 'low' => ['warning', 'Menipis'], 'out' => ['danger', 'Habis']][$bouquet->stock_status];
                @endphp
                <tr>
                    <td><img src="{{ $bouquet->image_url }}" alt="" style="width: 56px; height: 42px; object-fit: cover" class="rounded"></td>
                    <td>
                        {{ $bouquet->name }}
                        @unless($bouquet->is_active) <span class="badge bg-secondary">Nonaktif</span> @endunless
                    </td>
                    <td class="text-muted">{{ $bouquet->category->name }}</td>
                    <td>Rp {{ number_format($bouquet->price, 0, ',', '.') }}</td>
                    <td>
                        {{ $bouquet->stock }}
                        <span class="badge bg-{{ $badge[0] }}">{{ $badge[1] }}</span>
                    </td>
                    <td>
                        <form method="POST" action="{{ route('admin.bouquets.stock', $bouquet) }}" class="d-flex gap-1">
                            @csrf @method('PATCH')
                            <input type="number" name="stock" min="0" value="{{ $bouquet->stock }}"
                                   class="form-control form-control-sm" style="width: 84px">
                            <button class="btn btn-sm btn-outline-secondary">Simpan</button>
                        </form>
                    </td>
                    <td class="text-end">
                        <a href="{{ route('admin.bouquets.edit', $bouquet) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                        <form action="{{ route('admin.bouquets.destroy', $bouquet) }}" method="POST" class="d-inline"
                              onsubmit="return confirm('Hapus bouquet ini?')">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger">Hapus</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center text-muted py-4">Tidak ada bouquet.</td></tr>
            @endforelse
            </tbody>
        </table>
        </div>
    </div>
    <div class="mt-3">{{ $bouquets->links() }}</div>
</div>
@endsection