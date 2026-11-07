@extends('layouts.app')

@section('content')
<div class="container py-4">
    @include('admin._nav')
    <h1 class="h3 mb-3">Gambar Bouquet</h1>

    @if(session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif

    <div class="row g-3">
        @foreach($bouquets as $bouquet)
            <div class="col-6 col-md-4 col-lg-3">
                <div class="card h-100">
                    <img src="{{ $bouquet->image_url }}" class="card-img-top" alt="{{ $bouquet->name }}"
                         style="aspect-ratio: 4/3; object-fit: cover;">
                    <div class="card-body">
                        <div class="fw-semibold">{{ $bouquet->name }}</div>
                        <div class="text-muted small">{{ $bouquet->category->name }}</div>
                    </div>
                    <div class="card-footer bg-transparent">
                        <a href="{{ route('admin.bouquet-images.edit', $bouquet) }}" class="btn btn-sm btn-outline-primary w-100">
                            {{ $bouquet->image ? 'Ganti gambar' : 'Upload gambar' }}
                        </a>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
    <div class="mt-3">{{ $bouquets->links() }}</div>
</div>
@endsection