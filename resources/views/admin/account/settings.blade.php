@extends('layouts.admin')
@section('title', 'Pengaturan Akun')

@section('content')
@php $r = fn (string $name, ...$p) => route('admin.' . $name, ...$p); @endphp
<div class="admin-head">
    <h1 class="h3 mb-1">Pengaturan Akun</h1>
    <p>Keamanan login akun admin. Pengaturan toko ada di <a href="{{ route('admin.settings.edit') }}">Studio Settings</a>.</p>
</div>

@include('catalog._flash')
@include('admin.account._tabs')

<div style="max-width: 760px">
    <div class="panel mb-4">
        <h2 class="panel-title">Keamanan</h2>
        @include('account._security')
    </div>
    <div class="panel">
        <h2 class="panel-title">Notifikasi Email</h2>
        <p class="small mb-0">Email keamanan (ganti password / email) dikirim ke <strong>{{ $user->email }}</strong>.
            Akun admin tidak bisa dihapus dari sini agar toko tidak kehilangan akses pengelola.</p>
    </div>
</div>
@endsection
