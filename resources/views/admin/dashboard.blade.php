@extends('layouts.app')

@section('content')
<div class="container py-4">
    <h1 class="h3 mb-4">Dashboard Admin</h1>

    <div class="mb-4">
        <a href="{{ route('admin.categories.index') }}" class="btn btn-outline-primary">Kelola Kategori</a>
        <a href="{{ route('admin.bouquets.index') }}" class="btn btn-outline-primary">Kelola Bouquet</a>
        <a href="{{ route('admin.bouquet-images.index') }}" class="btn btn-outline-primary">Gambar Bouquet</a>
    </div>

    <div class="row g-3">
        <div class="col-md-4"><div class="card p-3"><div class="text-muted">Customer</div><div class="fs-3">{{ $customers }}</div></div></div>
        <div class="col-md-4"><div class="card p-3"><div class="text-muted">Bouquet</div><div class="fs-3">{{ $bouquets }}</div></div></div>
        <div class="col-md-4"><div class="card p-3"><div class="text-muted">Order</div><div class="fs-3">{{ $orders }}</div></div></div>
    </div>

    @if($lowStock->isNotEmpty())
        <div class="card border-warning mt-4">
            <div class="card-header bg-warning-subtle d-flex justify-content-between">
                <span>Peringatan stok ({{ $lowStockCount }} bouquet)</span>
                <a href="{{ route('admin.bouquets.index', ['stock' => 'low']) }}" class="small">Lihat semua</a>
            </div>
            <ul class="list-group list-group-flush">
                @foreach($lowStock as $item)
                    <li class="list-group-item d-flex justify-content-between">
                        <span>{{ $item->name }}</span>
                        <span class="badge {{ $item->stock <= 0 ? 'bg-danger' : 'bg-warning text-dark' }}">
                            {{ $item->stock <= 0 ? 'Habis' : 'Sisa ' . $item->stock }}
                        </span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</div>
@endsection