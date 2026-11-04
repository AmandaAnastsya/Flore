@extends('layouts.app')

@section('content')
<div class="container py-4" style="max-width: 860px">
    <h1 class="h3 mb-3">Edit Bouquet</h1>
    <form method="POST" action="{{ route('admin.bouquets.update', $bouquet) }}" enctype="multipart/form-data">
        @method('PUT')
        @include('admin.bouquets._form')
    </form>
</div>
@endsection