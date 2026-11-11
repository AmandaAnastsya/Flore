@extends('layouts.app')
@section('title', 'Register')

@section('content')
<div class="container">
    <div class="auth-wrap wide">
        <div class="auth-card">
            <div class="eyebrow text-center mb-1">Join Floré</div>
            <h1 class="mb-1">Buat Akun Baru</h1>
            <p class="text-muted small text-center mb-4">Simpan riwayat pesanan dan dapatkan pengingat momen spesialmu.</p>

            <form method="POST" action="{{ route('register') }}">
                @csrf
                <div class="row g-3">
                    <div class="col-12">
                        <label for="name" class="form-label">Nama lengkap</label>
                        <input id="name" type="text" class="form-control @error('name') is-invalid @enderror"
                               name="name" value="{{ old('name') }}" required autocomplete="name" autofocus>
                        @error('name') <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span> @enderror
                    </div>

                    <div class="col-md-6">
                        <label for="email" class="form-label">Email</label>
                        <input id="email" type="email" class="form-control @error('email') is-invalid @enderror"
                               name="email" value="{{ old('email') }}" required autocomplete="email">
                        @error('email') <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span> @enderror
                    </div>

                    <div class="col-md-6">
                        <label for="phone" class="form-label">No. HP</label>
                        <input id="phone" type="text" class="form-control @error('phone') is-invalid @enderror"
                               name="phone" value="{{ old('phone') }}" required placeholder="08123456789">
                        @error('phone') <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span> @enderror
                    </div>

                    <div class="col-12">
                        <label for="date_of_birth" class="form-label">Tanggal lahir <span class="text-muted">(opsional, untuk pengingat ulang tahun)</span></label>
                        <input id="date_of_birth" type="date" class="form-control @error('date_of_birth') is-invalid @enderror"
                               name="date_of_birth" value="{{ old('date_of_birth') }}" max="{{ now()->toDateString() }}">
                        @error('date_of_birth') <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span> @enderror
                    </div>

                    <div class="col-md-6">
                        <label for="password" class="form-label">Password</label>
                        <input id="password" type="password" class="form-control @error('password') is-invalid @enderror"
                               name="password" required autocomplete="new-password">
                        @error('password') <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span> @enderror
                    </div>

                    <div class="col-md-6">
                        <label for="password-confirm" class="form-label">Konfirmasi password</label>
                        <input id="password-confirm" type="password" class="form-control" name="password_confirmation" required autocomplete="new-password">
                    </div>
                </div>

                <button type="submit" class="btn btn-plum w-100 py-2 mt-4">Daftar</button>
            </form>

            <p class="text-center small text-muted mt-4 mb-0">
                Sudah punya akun? <a href="{{ route('login') }}">Masuk</a>
            </p>
        </div>
    </div>
</div>
@endsection
