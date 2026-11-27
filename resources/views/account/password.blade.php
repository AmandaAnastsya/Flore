@extends('layouts.app')
@section('title', 'Ganti Password')

@section('content')
<div class="container py-4" style="max-width: 560px">
    <nav class="small mb-3"><a href="{{ route('account.settings') }}" class="text-decoration-none">← Pengaturan Akun</a></nav>
    <h1 class="page-title h3 mb-1">Ganti Password</h1>
    <p class="text-muted small mb-4">Akun {{ auth()->user()->email }}</p>

    @include('catalog._flash')

    <div class="panel">
        @include('account._password-form', ['action' => route('account.password.update'), 'min' => $min])
    </div>

    <p class="small text-muted mt-3">Lupa password saat ini? Logout lalu pakai <a href="{{ route('password.request') }}">Lupa password</a> di halaman login.</p>
</div>
@endsection
