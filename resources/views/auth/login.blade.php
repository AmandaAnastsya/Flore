@extends('layouts.app')
@section('title', 'Login')

@section('content')
<div class="container">
    <div class="auth-wrap">
        <div class="auth-card">
            <div class="eyebrow text-center mb-1">Welcome back</div>
            <h1 class="mb-1">Masuk ke Floré</h1>
            <p class="text-muted small text-center mb-4">Lacak pesanan dan dapatkan rekomendasi bouquet personal.</p>

            <form method="POST" action="{{ route('login') }}">
                @csrf
                <div class="mb-3">
                    <label for="email" class="form-label">Email</label>
                    <input id="email" type="email" class="form-control @error('email') is-invalid @enderror"
                           name="email" value="{{ old('email') }}" required autocomplete="email" autofocus>
                    @error('email') <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span> @enderror
                </div>

                <div class="mb-3">
                    <div class="d-flex justify-content-between">
                        <label for="password" class="form-label">Password</label>
                        @if (Route::has('password.request'))
                            <a class="small" href="{{ route('password.request') }}">Lupa password?</a>
                        @endif
                    </div>
                    <input id="password" type="password" class="form-control @error('password') is-invalid @enderror"
                           name="password" required autocomplete="current-password">
                    @error('password') <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span> @enderror
                </div>

                <div class="form-check mb-4">
                    <input class="form-check-input" type="checkbox" name="remember" id="remember" {{ old('remember') ? 'checked' : '' }}>
                    <label class="form-check-label small" for="remember">Ingat saya</label>
                </div>

                <button type="submit" class="btn btn-plum w-100 py-2">Masuk</button>
            </form>

            @if (Route::has('register'))
                <p class="text-center small text-muted mt-4 mb-0">
                    Belum punya akun? <a href="{{ route('register') }}">Daftar sekarang</a>
                </p>
            @endif
        </div>
    </div>
</div>
@endsection
