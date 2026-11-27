@extends('layouts.app')
@section('title', 'Pengaturan Akun')

@section('content')
@php $r = fn (string $name, ...$p) => route($name, ...$p); @endphp
<div class="container py-4" style="max-width: 880px">
    <div class="eyebrow">My Account</div>
    <h1 class="page-title h3 mb-1">Pengaturan Akun</h1>
    <p class="text-muted small mb-4">Keamanan login, notifikasi email, dan privasi akunmu.</p>

    @include('catalog._flash')
    @include('account._tabs')

    <div class="panel mb-4">
        <h2 class="panel-title">Keamanan</h2>
        @include('account._security')
    </div>

    <div class="panel mb-4">
        <h2 class="panel-title">Notifikasi Email</h2>
        <form method="POST" action="{{ route('account.notifications.update') }}" class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3 pb-3 border-bottom">
            @csrf @method('PUT')
            <div>
                <div class="form-check form-switch mb-0">
                    <input type="hidden" name="order_updates_enabled" value="0">
                    <input class="form-check-input" type="checkbox" role="switch" id="order_updates_enabled" name="order_updates_enabled" value="1"
                           @checked($user->order_updates_enabled) onchange="this.form.requestSubmit ? this.form.requestSubmit() : this.form.submit()">
                    <label class="form-check-label small fw-semibold" for="order_updates_enabled">Email update status pesanan</label>
                </div>
                <div class="small text-muted">Bukti transfer diverifikasi, pesanan dirangkai, dikirim kurir, dan terkirim.
                    Invoice, kuitansi lunas, bukti ditolak & pembatalan <strong>selalu</strong> dikirim.</div>
            </div>
            <noscript><button class="btn btn-sm btn-outline-plum">Simpan</button></noscript>
        </form>
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div class="small">
                Email pengingat momen spesial:
                <strong class="{{ $user->reminders_enabled ? 'text-success' : 'text-muted' }}">{{ $user->reminders_enabled ? 'Aktif · ' . $user->reminder_days . ' hari sebelumnya' : 'Nonaktif' }}</strong>
                <div class="text-muted">Email keamanan (ganti password / email) selalu dikirim ke {{ $user->email }}.</div>
            </div>
            <a href="{{ route('profile.edit') }}" class="btn btn-sm btn-outline-plum">Atur Pengingat</a>
        </div>
    </div>

    <div class="panel danger-zone">
        <h2 class="panel-title text-danger">Hapus Akun</h2>
        <p class="small mb-2">
            Data pribadi (nama, email, no. HP, tanggal penting, momen spesial, foto) akan dihapus dan kamu tidak bisa login lagi.
            Riwayat pesanan tetap disimpan <strong>tanpa identitasmu</strong> untuk pembukuan toko.
        </p>
        @if($openOrders)
            <div class="alert alert-warning small mb-0">Kamu masih punya {{ $openOrders }} pesanan yang sedang berjalan. Akun bisa dihapus setelah semuanya selesai atau dibatalkan.</div>
        @else
            <details @if($errors->deleteAccount->any()) open @endif>
                <summary class="btn btn-sm btn-outline-danger">Saya ingin menghapus akun</summary>
                <form method="POST" action="{{ route('account.destroy') }}" class="mt-3" style="max-width: 420px">
                    @csrf @method('DELETE')
                    <label for="delete_password" class="form-label small mb-1">Password saat ini</label>
                    <input id="delete_password" type="password" name="password" required autocomplete="current-password"
                           class="form-control form-control-sm mb-2 @error('password', 'deleteAccount') is-invalid @enderror">
                    @error('password', 'deleteAccount') <div class="invalid-feedback mb-2">{{ $message }}</div> @enderror
                    <label for="confirmation" class="form-label small mb-1">Ketik <strong>HAPUS</strong> untuk konfirmasi</label>
                    <input id="confirmation" name="confirmation" required autocomplete="off"
                           class="form-control form-control-sm mb-2 @error('confirmation', 'deleteAccount') is-invalid @enderror">
                    @error('confirmation', 'deleteAccount') <div class="invalid-feedback mb-2">{{ $message }}</div> @enderror
                    <button class="btn btn-sm btn-danger">Hapus Akun Permanen</button>
                </form>
            </details>
        @endif
    </div>
</div>
@endsection
