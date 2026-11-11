@extends('layouts.admin')

@section('content')
<div class="container-fluid px-0" style="max-width: 860px">
    @include('admin._nav')
    <h1 class="h3 mb-3">Edit Bouquet</h1>
    <form method="POST" action="{{ route('admin.bouquets.update', $bouquet) }}" enctype="multipart/form-data">
        @method('PUT')
        @include('admin.bouquets._form')
    </form>
</div>
@endsection