@extends('layouts.app')

@section('content')
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8 text-center">
            <h1 class="display-5 mb-3">Selamat datang di Flore</h1>
            <p class="lead text-muted">Proyek Laravel + Bootstrap kamu sudah berjalan.</p>

            @guest
                <a href="{{ route('login') }}" class="btn btn-primary me-2">Login</a>
                <a href="{{ route('register') }}" class="btn btn-outline-primary">Register</a>
            @else
                <a href="{{ url('/home') }}" class="btn btn-primary">Ke Dashboard</a>
            @endguest
        </div>
    </div>
</div>
@endsection