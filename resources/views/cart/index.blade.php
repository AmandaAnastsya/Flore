@extends('layouts.app')

@section('content')
@include('catalog._styles')

<div class="container py-4 catalog">
    <h1 class="h4 section-title mb-3">Keranjang Belanja</h1>

    @include('catalog._flash')
    @foreach($notices as $notice)
        <div class="alert alert-warning">{{ $notice }}</div>
    @endforeach

    @if($lines->isEmpty())
        <div class="text-center text-muted py-5">
            Keranjangmu masih kosong. <a href="{{ route('catalog.index') }}">Lihat katalog</a>
        </div>
    @else
        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm">
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th></th><th>Bouquet</th>
                                    <th class="text-end">Harga</th>
                                    <th class="text-center">Jumlah</th>
                                    <th class="text-end">Subtotal</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                            @foreach($lines as $line)
                                @php
                                    $bouquet = $line['bouquet'];
                                    $max = min($bouquet->stock, \App\Services\Cart::MAX_QTY);
                                @endphp
                                <tr>
                                    <td style="width: 72px">
                                        <img src="{{ $bouquet->image_url }}" alt="" class="rounded" style="width: 64px; height: 48px; object-fit: cover">
                                    </td>
                                    <td>
                                        <a href="{{ route('catalog.show', $bouquet) }}" class="text-decoration-none fw-semibold">{{ $bouquet->name }}</a>
                                        <div class="small text-muted">
                                            {{ $bouquet->category->name }}
                                            @if($bouquet->stock_status === 'low') · <span class="text-warning-emphasis">Sisa {{ $bouquet->stock }}</span> @endif
                                        </div>
                                    </td>
                                    <td class="text-end">Rp {{ number_format($bouquet->price, 0, ',', '.') }}</td>
                                    <td class="text-center">
                                        <form method="POST" action="{{ route('cart.update', $bouquet) }}" class="d-inline-flex gap-1">
                                            @csrf @method('PATCH')
                                            <input type="number" name="quantity" value="{{ $line['quantity'] }}" min="1" max="{{ $max }}"
                                                   class="form-control form-control-sm" style="width: 72px" aria-label="Jumlah {{ $bouquet->name }}">
                                            <button class="btn btn-sm btn-outline-secondary">Ubah</button>
                                        </form>
                                        <div class="small text-muted">maks {{ $max }}</div>
                                    </td>
                                    <td class="text-end">Rp {{ number_format($line['subtotal'], 0, ',', '.') }}</td>
                                    <td class="text-end">
                                        <form method="POST" action="{{ route('cart.destroy', $bouquet) }}">
                                            @csrf @method('DELETE')
                                            <button class="btn btn-sm btn-outline-danger" aria-label="Hapus {{ $bouquet->name }}">Hapus</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <form method="POST" action="{{ route('cart.clear') }}" class="mt-3"
                      onsubmit="return confirm('Kosongkan seluruh keranjang?')">
                    @csrf @method('DELETE')
                    <button class="btn btn-sm btn-link text-danger p-0">Kosongkan keranjang</button>
                </form>
            </div>

            <div class="col-lg-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <h2 class="h6 section-title mb-3">Ringkasan</h2>
                        <div class="d-flex justify-content-between small text-muted mb-1">
                            <span>Jumlah barang</span>
                            <span>{{ $itemCount }} barang</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Subtotal</span>
                            <strong class="price">Rp {{ number_format($subtotal, 0, ',', '.') }}</strong>
                        </div>
                        <div class="small text-muted mb-3">Ongkos kirim dan diskon dihitung saat checkout.</div>

                        @if(Route::has('checkout.index'))
                            <a href="{{ route('checkout.index') }}" class="btn btn-rose w-100 mb-2">Lanjut ke checkout</a>
                        @else
                            {{-- diaktifkan di item Checkout --}}
                            <button class="btn btn-rose w-100 mb-2" disabled>Lanjut ke checkout (segera hadir)</button>
                        @endif
                        <a href="{{ route('catalog.index') }}" class="btn btn-outline-secondary w-100">Lanjut belanja</a>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
@endsection