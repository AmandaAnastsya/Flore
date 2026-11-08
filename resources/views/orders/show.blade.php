@extends('layouts.app')

@section('content')
@include('catalog._styles')
@php
    $date = $order->delivery_date->locale('id')->translatedFormat('l, d F Y');
    $statusLabels = [
        'created' => 'Dibuat', 'pending_payment' => 'Menunggu pembayaran', 'payment_confirmed' => 'Pembayaran dikonfirmasi',
        'preparing' => 'Sedang disiapkan', 'out_for_delivery' => 'Dalam pengiriman', 'delivered' => 'Terkirim', 'cancelled' => 'Dibatalkan',
    ];
    $paymentLabels = ['unpaid' => 'Belum dibayar', 'proof_uploaded' => 'Bukti diunggah', 'confirmed' => 'Lunas', 'rejected' => 'Ditolak'];
@endphp

<div class="container py-4 catalog">
    @include('catalog._flash')

    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
        <h1 class="h4 section-title mb-0">Pesanan {{ $order->order_number }}</h1>
        <div>
            <span class="badge bg-warning text-dark">{{ $statusLabels[$order->status] ?? $order->status }}</span>
            <span class="badge bg-secondary">{{ $paymentLabels[$order->payment_status] ?? $order->payment_status }}</span>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-transparent fw-semibold">Barang</div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead><tr><th>Barang</th><th class="text-end">Harga</th><th class="text-center">Jumlah</th><th class="text-end">Subtotal</th></tr></thead>
                        <tbody>
                        @foreach($order->items as $item)
                            <tr>
                                <td>{{ $item->name }} @if($item->isStem()) <span class="small text-muted">(per tangkai)</span> @endif</td>
                                <td class="text-end">Rp {{ number_format($item->price, 0, ',', '.') }}</td>
                                <td class="text-center">{{ $item->quantity }}{{ $item->isStem() ? ' tangkai' : '' }}</td>
                                <td class="text-end">Rp {{ number_format($item->subtotal, 0, ',', '.') }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card border-0 shadow-sm"><div class="card-body">
                <h2 class="h6 section-title mb-3">Pengiriman & Kartu Ucapan</h2>
                <dl class="row mb-0">
                    <dt class="col-sm-4">Penerima</dt><dd class="col-sm-8">{{ $order->recipient_name }} · {{ $order->recipient_phone }}</dd>
                    <dt class="col-sm-4">Alamat</dt><dd class="col-sm-8">{{ $order->delivery_address }}, {{ $order->delivery_city }}</dd>
                    <dt class="col-sm-4">Jadwal</dt><dd class="col-sm-8">{{ $date }}, {{ $slots[$order->delivery_slot] ?? '-' }}</dd>
                    <dt class="col-sm-4">Pesan kartu</dt><dd class="col-sm-8">{{ $order->card_message ?? '—' }}</dd>
                    <dt class="col-sm-4">Dari</dt><dd class="col-sm-8">{{ $order->is_anonymous ? 'Anonim' : ($order->sender_name ?? '—') }}</dd>
                </dl>
            </div></div>
        </div>

        <div class="col-lg-4">
            <div class="card border-0 shadow-sm"><div class="card-body">
                <h2 class="h6 section-title mb-3">Total</h2>
                <div class="d-flex justify-content-between small mb-1"><span>Subtotal</span><span>Rp {{ number_format($order->subtotal, 0, ',', '.') }}</span></div>
                <div class="d-flex justify-content-between small mb-1"><span>Ongkir</span><span>Rp {{ number_format($order->delivery_fee, 0, ',', '.') }}</span></div>
                @if($order->discount > 0)
                    <div class="d-flex justify-content-between small mb-1"><span>Diskon</span><span>− Rp {{ number_format($order->discount, 0, ',', '.') }}</span></div>
                @endif
                <hr>
                <div class="d-flex justify-content-between mb-3"><strong>Total</strong><strong class="price">Rp {{ number_format($order->total_amount, 0, ',', '.') }}</strong></div>
                <div class="small text-muted mb-3">Instruksi dan unggah bukti pembayaran akan tersedia di langkah berikutnya.</div>
                <a href="{{ route('catalog.index') }}" class="btn btn-outline-secondary w-100">Lanjut belanja</a>
            </div></div>
        </div>
    </div>
</div>
@endsection