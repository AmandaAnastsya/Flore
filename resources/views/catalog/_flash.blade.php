@if(session('success'))
    <div class="alert alert-success d-flex flex-wrap justify-content-between align-items-center gap-2" role="status">
        <span>{{ session('success') }}</span>
        {{-- tautan keranjang hanya relevan di halaman belanja (bug UI-02: sebelumnya muncul juga di pesanan & admin) --}}
        @if(request()->routeIs('landing', 'catalog.*', 'stems.*', 'home'))
            <a href="{{ route('cart.index') }}" class="alert-link">Lihat keranjang</a>
        @endif
    </div>
@endif
@if(session('error'))
    <div class="alert alert-danger" role="alert">{{ session('error') }}</div>
@endif
@if($errors->has('quantity'))
    <div class="alert alert-danger" role="alert">{{ $errors->first('quantity') }}</div>
@endif
