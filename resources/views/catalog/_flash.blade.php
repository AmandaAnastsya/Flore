@if(session('success'))
    <div class="alert alert-success d-flex justify-content-between align-items-center">
        <span>{{ session('success') }}</span>
        @unless(request()->routeIs('cart.index'))
            <a href="{{ route('cart.index') }}" class="alert-link">Lihat keranjang</a>
        @endunless
    </div>
@endif
@if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif
@if($errors->has('quantity'))
    <div class="alert alert-danger">{{ $errors->first('quantity') }}</div>
@endif