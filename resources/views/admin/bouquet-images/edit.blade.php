@extends('layouts.admin')

@section('content')
<div class="container-fluid px-0" style="max-width: 640px">
    @include('admin._nav')
    <h1 class="h3 mb-3">Gambar: {{ $bouquet->name }}</h1>

    <img id="preview" src="{{ $bouquet->image_url }}" class="img-fluid rounded mb-3" alt="Preview">

    <form method="POST" action="{{ route('admin.bouquet-images.update', $bouquet) }}" enctype="multipart/form-data">
        @csrf @method('PUT')
        <div class="mb-3">
            <label for="image" class="form-label">Pilih gambar (JPG/PNG/WEBP, maks 2 MB)</label>
            <input id="image" type="file" name="image" accept="image/jpeg,image/png,image/webp"
                   class="form-control @error('image') is-invalid @enderror" required
                   onchange="document.getElementById('preview').src = URL.createObjectURL(this.files[0])">
            @error('image') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <button class="btn btn-primary">Upload</button>
        <a href="{{ route('admin.bouquet-images.index') }}" class="btn btn-outline-secondary">Batal</a>
    </form>

    @if($bouquet->image)
        <form method="POST" action="{{ route('admin.bouquet-images.destroy', $bouquet) }}" class="mt-3"
              onsubmit="return confirm('Hapus gambar ini?')">
            @csrf @method('DELETE')
            <button class="btn btn-sm btn-outline-danger">Hapus gambar</button>
        </form>
    @endif
</div>
@endsection