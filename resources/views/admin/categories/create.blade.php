@extends('layouts.admin')

@section('content')
<div class="container-fluid px-0" style="max-width: 640px">
    @include('admin._nav')
    <h1 class="h3 mb-3">Tambah Kategori</h1>
    <form method="POST" action="{{ route('admin.categories.store') }}">
        @include('admin.categories._form')
    </form>
</div>
@endsection