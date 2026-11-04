@extends('layouts.app')

@section('content')
<div class="container py-4" style="max-width: 640px">
    <h1 class="h3 mb-3">Tambah Kategori</h1>
    <form method="POST" action="{{ route('admin.categories.store') }}">
        @include('admin.categories._form')
    </form>
</div>
@endsection