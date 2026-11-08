@extends('layouts.app')

@section('content')
@include('catalog._styles')

<div class="container py-4 catalog">
    <h1 class="h4 section-title mb-3">Pesanan Saya</h1>
    @include('catalog._flash')

    <form method="GET" action="{{ route('orders.index') }}" class="row g-2 mb-3">
        <div class="col-auto">
            <select name="status" class="form-select" onchange="this.form.submit()" aria-label="Filter status">
                <option value="">Semua status</option>
                @foreach(\App\Models\Order::STATUS_LABELS as $key => $label)
                    @continue($key === 'created')
                    <option value="{{ $key }}" @selected($status === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        @if($status)
            <div class="col-auto align-self-center"><a href="{{ route('orders.index') }}" class="small">Reset</a></div>
        @endif
    </form>

    @if($orders->isEmpty())
        <div class="text-center text-muted py-5">
            Belum ada pesanan. <a href="{{ route('catalog.index') }}">Lihat katalog</a>
        </div>
    @else
        <div class="card border-0 shadow-sm">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr><th>No. Pesanan</th><th>Dipesan</th><th>Dikirim</th><th class="text-center">Barang</th><th class="text-end">Total</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                    @foreach($orders as $order)
                        <tr>
                            <td><a href="{{ route('orders.show', $order->order_number) }}" class="text-decoration-none fw-semibold">{{ $order->order_number }}</a></td>
                            <td>{{ $order->created_at->format('d M Y') }}</td>
                            <td>{{ $order->delivery_date->format('d M Y') }}</td>
                            <td class="text-center">{{ $order->items_count }}</td>
                            <td class="text-end">Rp {{ number_format($order->total_amount, 0, ',', '.') }}</td>
                            <td>
                                <span class="badge bg-{{ \App\Models\Order::STATUS_BADGES[$order->status] ?? 'secondary' }}">{{ \App\Models\Order::STATUS_LABELS[$order->status] ?? $order->status }}</span>
                                @if($order->status === 'pending_payment')
                                    <div class="small text-muted">{{ \App\Models\Order::PAYMENT_LABELS[$order->payment_status] ?? '' }}</div>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        <div class="mt-3">{{ $orders->links() }}</div>
    @endif
</div>
@endsection