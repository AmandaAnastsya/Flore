@extends('layouts.admin')
@section('title', 'Edit Kurir')

@section('content')
<nav class="mb-3 small" aria-label="Breadcrumb">
    <a href="{{ route('admin.couriers.index') }}" class="text-decoration-none text-mauve">&larr; Armada Kurir</a>
</nav>

<div class="admin-head">
    <h1 class="h3 mb-1">Edit Kurir: {{ $courier->name }}</h1>
    <p>Kurir yang sedang membawa pesanan tidak bisa dinonaktifkan sebelum pesanannya selesai atau di-re-route.</p>
</div>

@include('catalog._flash')

<div class="panel" style="max-width: 560px">
    <form method="POST" action="{{ route('admin.couriers.update', $courier) }}">
        @csrf @method('PUT')
        @include('admin.couriers._form')
        <div class="d-flex gap-2">
            <button class="btn btn-plum">Simpan Perubahan</button>
            <a href="{{ route('admin.couriers.index') }}" class="btn btn-link">Batal</a>
        </div>
    </form>
</div>
@endsection
