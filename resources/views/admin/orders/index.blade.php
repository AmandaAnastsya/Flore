@extends('layouts.app')

@section('content')
@include('admin._nav')
@php use App\Models\Order; @endphp

<div class="container py-4">
    <h1 class="h4 mb-3">Kelola Pesanan</h1>
    @include('catalog._flash')

    <div class="d-flex flex-wrap gap-2 mb-3">
        <a href="{{ route('admin.orders.index', ['payment_status' => 'proof_uploaded']) }}"
           class="btn btn-sm {{ $payment === 'proof_uploaded' ? 'btn-warning' : 'btn-outline-warning' }}">
            Perlu verifikasi <span class="badge bg-dark">{{ $awaiting }}</span>
        </a>
        <a href="{{ route('admin.orders.index') }}" class="btn btn-sm btn-outline-secondary">Semua</a>
    </div>

    <form method="GET" action="{{ route('admin.orders.index') }}" class="row g-2 mb-3">
        <div class="col-12 col-md-4">
            <input type="text" name="q" value="{{ $q }}" maxlength="100" class="form-control" placeholder="No. pesanan atau nama penerima">
        </div>
        <div class="col-6 col-md-3">
            <select name="status" class="form-select" aria-label="Status">
                <option value="">Semua status</option>
                @foreach(Order::STATUS_LABELS as $key => $label)
                    @continue($key === 'created')
                    <option value="{{ $key }}" @selected($status === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-6 col-md-3">
            <select name="payment_status" class="form-select" aria-label="Pembayaran">
                <option value="">Semua pembayaran</option>
                @foreach(Order::PAYMENT_LABELS as $key => $label)
                    <option value="{{ $key }}" @selected($payment === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-auto"><button class="btn btn-secondary">Filter</button></div>
    </form>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr><th>No. Pesanan</th><th>Customer</th><th>Kirim</th><th class="text-end">Total</th><th>Status</th><th>Pembayaran</th></tr>
                </thead>
                <tbody>
                @forelse($orders as $order)
                    <tr>
                        <td><a href="{{ route('admin.orders.show', $order) }}" class="text-decoration-none fw-semibold">{{ $order->order_number }}</a></td>
                        <td>{{ $order->recipient_name }}<div class="small text-muted">{{ $order->user?->name }}</div></td>
                        <td>{{ $order->delivery_date->format('d M Y') }}</td>
                        <td class="text-end">Rp {{ number_format($order->total_amount, 0, ',', '.') }}</td>
                        <td><span class="badge bg-{{ Order::STATUS_BADGES[$order->status] ?? 'secondary' }}">{{ Order::STATUS_LABELS[$order->status] ?? $order->status }}</span></td>
                        <td>{{ Order::PAYMENT_LABELS[$order->payment_status] ?? $order->payment_status }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-4">Tidak ada pesanan yang cocok.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">{{ $orders->links() }}</div>
</div>
@endsection