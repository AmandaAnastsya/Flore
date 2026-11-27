@extends('layouts.app')
@section('title', 'Profil Saya')

@section('content')
@php $r = fn (string $name, ...$p) => route($name, ...$p); @endphp
<div class="container py-4" style="max-width: 880px">
    <div class="eyebrow">My Account</div>
    <h1 class="page-title h3 mb-1">Profil Saya</h1>
    <p class="text-muted small mb-4">Member sejak {{ $user->created_at->locale('id')->translatedFormat('d F Y') }}</p>

    @include('catalog._flash')

    @include('account._tabs')

    <div class="row g-4">
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
                <h2 class="panel-title">Ringkasan Akun</h2>
                <dl class="account-stats mb-3">
                    <div><dt>Pesanan</dt><dd>{{ $stats['orders'] }}</dd></div>
                    <div><dt>Total belanja</dt><dd>Rp {{ number_format($stats['spent'], 0, ',', '.') }}</dd></div>
                    <div><dt>Momen spesial</dt><dd>{{ $stats['moments'] }}</dd></div>
                </dl>
                <a href="{{ route('orders.index') }}" class="btn btn-sm btn-outline-plum w-100 mb-2">Pesanan Saya</a>
                <a href="{{ route('profile.edit') }}" class="btn btn-sm btn-outline-plum w-100">Momen Spesial & Pengingat</a>
            </div>
        </div>
    </div>
</div>
@endsection
