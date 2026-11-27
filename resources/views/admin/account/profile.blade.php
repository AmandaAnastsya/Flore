@extends('layouts.admin')
@section('title', 'Profil Saya')

@section('content')
@php $r = fn (string $name, ...$p) => route('admin.' . $name, ...$p); @endphp
<div class="admin-head">
    <h1 class="h3 mb-1">Profil Saya</h1>
    <p>Administrator · bergabung {{ $user->created_at->locale('id')->translatedFormat('d F Y') }}</p>
</div>

@include('catalog._flash')
@include('admin.account._tabs')

<div class="row g-4" style="max-width: 980px">
    <div class="col-lg-8">
        <div class="panel mb-4">
            <h2 class="panel-title">Foto Profil</h2>
            @include('account._avatar')
        </div>
        <div class="panel">
            <h2 class="panel-title">Data Diri</h2>
            @include('account._profile-form')
        </div>
    </div>
    <div class="col-lg-4">
        <div class="panel">
            <h2 class="panel-title">Akses Cepat</h2>
            <a href="{{ route('admin.account.settings') }}" class="btn btn-sm btn-outline-plum w-100 mb-2">Pengaturan Akun & Keamanan</a>
            <a href="{{ route('admin.settings.edit') }}" class="btn btn-sm btn-outline-plum w-100">Studio Settings (toko)</a>
        </div>
    </div>
</div>
@endsection
