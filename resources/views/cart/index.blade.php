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
                                </tr>
                            </thead>
                            <tbody>
                            @foreach($lines as $line)
                                @php $bouquet = $line['bouquet']; @endphp
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
                                    <td class="text-center">{{ $line['quantity'] }}</td>
                                    <td class="text-end">Rp {{ number_format($line['subtotal'], 0, ',', '.') }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-2">
                            <span>Subtotal</span>
                            <strong class="price">Rp {{ number_format($subtotal, 0, ',', '.') }}</strong>
                        </div>
                        <div class="small text-muted mb-3">Ongkos kirim dan diskon dihitung saat checkout.</div>
                        {{-- diaktifkan di Part 2 / modul Checkout --}}
                        <button class="btn btn-rose w-100 mb-2" disabled>Lanjut ke checkout (segera hadir)</button>
                        <a href="{{ route('catalog.index') }}" class="btn btn-outline-secondary w-100">Lanjut belanja</a>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
@endsection