@extends('layouts.app')

@section('content')
<div class="container py-4" style="max-width: 640px">
    @include('admin._nav')
    <h1 class="h3 mb-3">Edit Kategori</h1>
    <form method="POST" action="{{ route('admin.categories.update', $category) }}">
        @method('PUT')
        @include('admin.categories._form')
    </form>
</div>
@endsection