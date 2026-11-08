@extends('layouts.app')

@section('content')
@include('catalog._styles')
@php
    $date = \Illuminate\Support\Carbon::parse($data['delivery_date'])->locale('id')->translatedFormat('l, d F Y');
@endphp

<div class="container py-4 catalog">
    <h1 class="h4 section-title mb-3">Tinjau Pesanan</h1>
    @include('catalog._flash')

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm mb-4"><div class="card-body">
                <h2 class="h6 section-title mb-3">Penerima & Pengiriman</h2>
                <dl class="row mb-0">
                    <dt class="col-sm-4">Penerima</dt><dd class="col-sm-8">{{ $data['recipient_name'] }} · {{ $data['recipient_phone'] }}</dd>
                    <dt class="col-sm-4">Alamat</dt><dd class="col-sm-8">{{ $data['delivery_address'] }}, {{ $data['delivery_city'] }}</dd>
                    <dt class="col-sm-4">Jadwal</dt><dd class="col-sm-8">{{ $date }}, {{ $slots[$data['delivery_slot']] }}</dd>
                </dl>
            </div></div>

            <div class="card border-0 shadow-sm mb-4"><div class="card-body">
                <h2 class="h6 section-title mb-3">Kartu Ucapan</h2>
                <dl class="row mb-0">
                    <dt class="col-sm-4">Occasion</dt><dd class="col-sm-8">{{ !empty($data['occasion']) ? ucfirst(str_replace('-', ' ', $data['occasion'])) : '—' }}</dd>
                    <dt class="col-sm-4">Pesan</dt><dd class="col-sm-8">{{ $data['card_message'] ?? '—' }}</dd>
                    <dt class="col-sm-4">Dari</dt>
                    <dd class="col-sm-8">
                        @if($data['is_anonymous']) Anonim
                        @else {{ $data['sender_name'] ?? '—' }}
                        @endif
                    </dd>
                </dl>
            </div></div>

            <a href="{{ route('checkout.index') }}" class="btn btn-outline-secondary">Ubah data</a>
        </div>

        <div class="col-lg-4">
            <div class="card border-0 shadow-sm"><div class="card-body">
                <h2 class="h6 section-title mb-3">Barang</h2>
                @foreach($lines as $line)
                    <div class="d-flex justify-content-between small mb-2">
                        <span>{{ $line['bouquet']->name }} × {{ $line['quantity'] }}</span>
                        <span>Rp {{ number_format($line['subtotal'], 0, ',', '.') }}</span>
                    </div>
                @endforeach
                @foreach($stemLines as $line)
                    <div class="d-flex justify-content-between small mb-2">
                        <span>{{ $line['flower']->name }} × {{ $line['quantity'] }} tangkai</span>
                        <span>Rp {{ number_format($line['subtotal'], 0, ',', '.') }}</span>
                    </div>
                @endforeach
                <hr>
                <div class="d-flex justify-content-between mb-3">
                    <span>Subtotal</span><strong class="price">Rp {{ number_format($subtotal, 0, ',', '.') }}</strong>
                </div>
                <form method="POST" action="{{ route('checkout.place') }}" onsubmit="this.querySelector('button').disabled = true;">
    @csrf
    <button class="btn btn-rose w-100 mb-2">Buat pesanan</button>
</form>
<div class="small text-muted">Stok langsung dikurangi saat pesanan dibuat. Instruksi pembayaran menyusul di langkah berikutnya.</div>
            </div></div>
        </div>
    </div>
</div>
@endsection