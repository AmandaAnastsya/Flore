@extends('layouts.app')
@section('title', 'Buku Alamat')

@section('content')
<div class="container py-4" style="max-width: 980px">
    <div class="eyebrow">My Account</div>
    <h1 class="page-title h3 mb-1">Buku Alamat</h1>
    <p class="text-muted small mb-4">Simpan alamat penerima yang sering kamu kirimi bunga — tinggal pilih saat checkout.</p>

    @include('catalog._flash')
    @include('account._tabs')

    <div class="row g-4">
        <div class="col-lg-7">
            @forelse($addresses as $a)
                <div class="address-card {{ $a->is_default ? 'default' : '' }}">
                    <div class="d-flex justify-content-between align-items-start gap-2">
                        <div class="fw-semibold">{{ $a->label }}</div>
                        @if($a->is_default) <span class="badge-soft primary">Utama</span> @endif
                    </div>
                    <div class="small mt-1">{{ $a->recipient_name }} · {{ $a->recipient_phone }}</div>
                    <div class="small text-muted">{{ $a->address }}, {{ $a->city }}</div>
                    <div class="d-flex flex-wrap gap-3 mt-2 small">
                        <a href="{{ route('account.addresses.edit', $a) }}">Edit</a>
                        @unless($a->is_default)
                            <form method="POST" action="{{ route('account.addresses.default', $a) }}">@csrf <button class="btn btn-link btn-sm p-0">Jadikan utama</button></form>
                        @endunless
                        <form method="POST" action="{{ route('account.addresses.destroy', $a) }}" onsubmit="return confirm('Hapus alamat {{ addslashes($a->label) }}?')">
                            @csrf @method('DELETE') <button class="btn btn-link btn-sm p-0 text-danger">Hapus</button>
                        </form>
                    </div>
                </div>
            @empty
                <div class="panel text-center text-muted small py-5">
                    Belum ada alamat tersimpan. Tambahkan lewat formulir, atau centang “Simpan ke Buku Alamat” saat checkout.
                </div>
            @endforelse
            <p class="small text-muted mt-2">{{ $addresses->count() }} / {{ $max }} alamat</p>
        </div>
        <div class="col-lg-5">
            <div class="panel">
                <h2 class="panel-title">+ Tambah Alamat Baru</h2>
                @if($addresses->count() >= $max)
                    <p class="small text-muted mb-0">Buku alamat sudah penuh ({{ $max }} alamat).</p>
                @else
                    <form method="POST" action="{{ route('account.addresses.store') }}" novalidate>
                        @csrf
                        @include('account._address-form')
                        <button class="btn btn-plum w-100 mt-3">Simpan Alamat</button>
                    </form>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
