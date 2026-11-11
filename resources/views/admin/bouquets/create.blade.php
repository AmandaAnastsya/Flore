@extends('layouts.admin')

@section('content')
<div class="container-fluid px-0" style="max-width: 860px">
    @include('admin._nav')
    <h1 class="h3 mb-3">Tambah Bouquet</h1>
    <form method="POST" action="{{ route('admin.bouquets.store') }}" enctype="multipart/form-data">
        @include('admin.bouquets._form')
    </form>
</div>
@endsection