@extends('layouts.app')
@section('title', 'Edit Alamat')

@section('content')
<div class="container py-4" style="max-width: 640px">
    <nav class="small mb-3"><a href="{{ route('account.addresses.index') }}" class="text-decoration-none">← Buku Alamat</a></nav>
    <h1 class="page-title h3 mb-4">Edit Alamat: {{ $address->label }}</h1>

    <form method="POST" action="{{ route('account.addresses.update', $address) }}" class="panel" novalidate>
        @csrf @method('PUT')
        @include('account._address-form')
        <div class="d-flex gap-2 mt-3">
            <button class="btn btn-plum">Simpan Perubahan</button>
            <a href="{{ route('account.addresses.index') }}" class="btn btn-link">Batal</a>
        </div>
    </form>
</div>
@endsection
