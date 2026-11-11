@extends('layouts.admin')

@section('content')
@include('admin._nav')
@php
    use App\Models\Order;
    $date = $order->delivery_date->locale('id')->translatedFormat('l, d F Y');
    $ext = $order->payment_proof ? strtolower(pathinfo($order->payment_proof, PATHINFO_EXTENSION)) : null;
    $next = $order->nextStatus();
@endphp

<div class="container-fluid px-0">
    @include('catalog._flash')

    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Pesanan {{ $order->order_number }}</h1>
        <div>
            <span class="badge bg-{{ Order::STATUS_BADGES[$order->status] ?? 'secondary' }}">{{ Order::STATUS_LABELS[$order->status] ?? $order->status }}</span>
            <span class="badge bg-secondary">{{ Order::PAYMENT_LABELS[$order->payment_status] ?? $order->payment_status }}</span>
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
                                <td class="text-center">{{ $item->quantity }}</td>
                                <td class="text-end">Rp {{ number_format($item->subtotal, 0, ',', '.') }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                        <tfoot>
                            <tr><th colspan="3" class="text-end">Total</th><th class="text-end">Rp {{ number_format($order->total_amount, 0, ',', '.') }}</th></tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <div class="card border-0 shadow-sm"><div class="card-body">
                <h2 class="h6 mb-3">Pengiriman & Kartu Ucapan</h2>
                <dl class="row mb-0">
                    <dt class="col-sm-4">Pemesan</dt><dd class="col-sm-8">{{ $order->user?->name }} ({{ $order->user?->email }})</dd>
                    <dt class="col-sm-4">Penerima</dt><dd class="col-sm-8">{{ $order->recipient_name }} · {{ $order->recipient_phone }}</dd>
                    <dt class="col-sm-4">Alamat</dt><dd class="col-sm-8">{{ $order->delivery_address }}, {{ $order->delivery_city }}</dd>
                    <dt class="col-sm-4">Jadwal</dt><dd class="col-sm-8">{{ $date }}, {{ $slots[$order->delivery_slot] ?? '-' }}</dd>
                    <dt class="col-sm-4">Occasion</dt><dd class="col-sm-8">{{ $order->occasion ? ucfirst(str_replace('-', ' ', $order->occasion)) : '—' }}</dd>
                    <dt class="col-sm-4">Pesan kartu</dt><dd class="col-sm-8">{{ $order->card_message ?? '—' }}</dd>
                    <dt class="col-sm-4">Dari</dt><dd class="col-sm-8">{{ $order->is_anonymous ? 'Anonim' : ($order->sender_name ?? '—') }}</dd>
                </dl>
            </div></div>
        </div>

        <div class="col-lg-4">
            <div class="card border-0 shadow-sm mb-4"><div class="card-body">
                <h2 class="h6 mb-3">Bukti Pembayaran</h2>
                @if($order->payment_proof)
                    @if($ext === 'pdf')
                        <a href="{{ route('orders.proof', $order->order_number) }}" target="_blank" class="btn btn-outline-secondary w-100 mb-3">Buka PDF bukti</a>
                    @else
                        <a href="{{ route('orders.proof', $order->order_number) }}" target="_blank">
                            <img src="{{ route('orders.proof', $order->order_number) }}" alt="Bukti pembayaran" class="img-fluid rounded mb-3" style="max-height: 360px">
                        </a>
                    @endif
                @else
                    <div class="text-muted small mb-3">Belum ada bukti.</div>
                @endif

                @if($order->isAwaitingVerification())
                    <form method="POST" action="{{ route('admin.orders.confirm', $order) }}" class="mb-2">
                        @csrf <button class="btn btn-success w-100">Konfirmasi pembayaran</button>
                    </form>
                    <form method="POST" action="{{ route('admin.orders.reject', $order) }}" onsubmit="return confirm('Tolak bukti ini?')">
                        @csrf <button class="btn btn-outline-danger w-100">Tolak bukti</button>
                    </form>
                @endif
                @if($order->paid_at)
                    <div class="small text-muted mt-2">Lunas: {{ $order->paid_at->format('d M Y H:i') }}</div>
                @endif
            </div></div>

            <div class="card border-0 shadow-sm"><div class="card-body">
                <h2 class="h6 mb-3">Status Pesanan</h2>
                @if($next)
                    <form method="POST" action="{{ route('admin.orders.advance', $order) }}" class="mb-2">
                        @csrf <button class="btn btn-primary w-100">Ubah ke: {{ Order::STATUS_LABELS[$next] }}</button>
                    </form>
                @elseif($order->status === 'pending_payment')
                    <div class="small text-muted mb-2">Menunggu pembayaran dikonfirmasi.</div>
                @endif

                @if($order->canBeCancelledByAdmin())
                    <form method="POST" action="{{ route('admin.orders.cancel', $order) }}" onsubmit="return confirm('Batalkan pesanan dan kembalikan stok?')">
                        @csrf <button class="btn btn-outline-danger w-100">Batalkan pesanan</button>
                    </form>
                @endif
                <a href="{{ route('admin.orders.index') }}" class="btn btn-link w-100 mt-2">← Semua pesanan</a>
            </div></div>
        </div>
    </div>
</div>
@endsection