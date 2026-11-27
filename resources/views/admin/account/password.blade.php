@extends('layouts.admin')
@section('title', 'Ganti Password')

@section('content')
<div class="admin-head">
    <h1 class="h3 mb-1">Akun & Password</h1>
    <p>{{ auth()->user()->name }} · {{ auth()->user()->email }}</p>
</div>

@include('catalog._flash')
@include('admin.account._tabs')

<div class="panel" style="max-width: 560px">
    <h2 class="panel-title">Ganti Password Admin</h2>
    <p class="small text-muted">Akun admin wajib memakai password minimal {{ $min }} karakter berisi huruf dan angka.</p>
    @include('account._password-form', ['action' => route('admin.password.update'), 'min' => $min])
</div>
@endsection
