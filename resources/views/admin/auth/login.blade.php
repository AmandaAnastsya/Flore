@extends('layouts.app')
@section('title', 'Login Admin')

@section('content')
<div class="container">
    <div class="auth-wrap">
        <div class="auth-card">
            <div class="eyebrow text-center mb-1">Admin Portal</div>
            <h1 class="mb-4">Floré Studio</h1>

            <form method="POST" action="{{ route('admin.login.submit') }}">
                @csrf
                <div class="mb-3">
                    <label for="email" class="form-label">Email</label>
                    <input id="email" type="email" name="email" value="{{ old('email') }}"
                           class="form-control @error('email') is-invalid @enderror" required autofocus>
                    @error('email') <span class="invalid-feedback">{{ $message }}</span> @enderror
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <input id="password" type="password" name="password" class="form-control" required>
                </div>
                <div class="form-check mb-4">
                    <input class="form-check-input" type="checkbox" name="remember" id="remember">
                    <label class="form-check-label small" for="remember">Ingat saya</label>
                </div>
                <button class="btn btn-plum w-100 py-2">Masuk</button>
            </form>
        </div>
    </div>
</div>
@endsection
