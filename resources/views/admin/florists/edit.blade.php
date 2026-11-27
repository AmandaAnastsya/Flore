@extends('layouts.admin')
@section('title', 'Edit Florist')

@section('content')
<nav class="mb-3 small"><a href="{{ route('admin.florists.index') }}" class="text-decoration-none text-mauve">&larr; Florist & Produksi</a></nav>
<div class="admin-head"><h1 class="h3 mb-1">Edit Florist: {{ $florist->name }}</h1></div>

<div class="panel" style="max-width: 560px">
    <form method="POST" action="{{ route('admin.florists.update', $florist) }}">
        @csrf @method('PUT')
        @include('admin.florists._form')
        <div class="d-flex gap-2">
            <button class="btn btn-plum">Simpan Perubahan</button>
            <a href="{{ route('admin.florists.index') }}" class="btn btn-link">Batal</a>
        </div>
    </form>
</div>
@endsection
