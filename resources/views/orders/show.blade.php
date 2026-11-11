@extends('layouts.app')

@section('content')
@include('catalog._styles')
@php
    use App\Models\Order;
    $date = $order->delivery_date->locale('id')->translatedFormat('l, d F Y');
    $currentIdx = array_search($order->status, Order::TIMELINE, true);
    $ext = $order->payment_proof ? strtolower(pathinfo($order->payment_proof, PATHINFO_EXTENSION)) : null;
@endphp

<div class="container py-4 catalog">
    @include('catalog._flash')

    <nav class="small mb-3"><a href="{{ route('orders.index') }}" class="text-decoration-none">← Pesanan Saya</a></nav>

    {{-- Banner status (mockup hal. 45) --}}
    <div class="status-banner mb-4 d-md-flex justify-content-between align-items-center gap-3">
        <div>
            <div class="eyebrow mb-1">Pesanan {{ $order->order_number }}</div>
            <h1 class="h5 mb-1 text-white text-uppercase" style="letter-spacing:.04em">
                Status: {{ Order::STATUS_LABELS[$order->status] ?? $order->status }}
            </h1>
            <div class="small" style="color:rgba(255,255,255,.8)">
                Pengiriman {{ $date }}{{ isset($slots[$order->delivery_slot]) ? ' · ' . $slots[$order->delivery_slot] : '' }}
                · Penerima: {{ $order->recipient_name }}
            </div>
        </div>
        <span class="badge bg-light text-dark mt-2 mt-md-0">{{ Order::PAYMENT_LABELS[$order->payment_status] ?? $order->payment_status }}</span>
    </div>

    {{-- Tracking --}}
    <div class="card border-0 shadow-sm mb-4"><div class="card-body">
        <h2 class="h6 section-title mb-4">Fulfillment Progress Timeline</h2>
        @if($order->status === 'cancelled')
            <div class="alert alert-secondary mb-0">
                Pesanan dibatalkan{{ $order->cancelled_at ? ' pada ' . $order->cancelled_at->format('d M Y H:i') : '' }}.
            </div>
        @else
            <ol class="timeline">
                @foreach(Order::TIMELINE as $i => $step)
                    @php
                        $done = $currentIdx !== false && $i < $currentIdx;
                        $now = $currentIdx !== false && $i === $currentIdx;
                    @endphp
                    <li class="{{ $done ? 'done' : ($now ? 'current' : '') }}" @if($now) aria-current="step" @endif>
                        {{ Order::STATUS_LABELS[$step] }}
                    </li>
                @endforeach
            </ol>
        @endif
    </div></div>

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
            <div class="card border-0 shadow-sm mb-4"><div class="card-body">
                <h2 class="h6 section-title mb-3">Total</h2>
                <div class="d-flex justify-content-between small mb-1"><span>Subtotal</span><span>Rp {{ number_format($order->subtotal, 0, ',', '.') }}</span></div>
                <div class="d-flex justify-content-between small mb-1"><span>Ongkir</span><span>Rp {{ number_format($order->delivery_fee, 0, ',', '.') }}</span></div>
                @if($order->discount > 0)
                    <div class="d-flex justify-content-between small mb-1"><span>Diskon</span><span>− Rp {{ number_format($order->discount, 0, ',', '.') }}</span></div>
                @endif
                <hr>
                <div class="d-flex justify-content-between"><strong>Total</strong><strong class="price">Rp {{ number_format($order->total_amount, 0, ',', '.') }}</strong></div>
            </div></div>

            {{-- Pembayaran --}}
            <div class="card border-0 shadow-sm mb-4"><div class="card-body">
                <h2 class="h6 section-title mb-3">Pembayaran</h2>

                @if($order->canUploadProof())
                    @if($order->payment_status === 'rejected')
                        <div class="alert alert-danger small">Bukti sebelumnya ditolak. Silakan unggah bukti yang benar.</div>
                    @endif
                    <div class="small mb-3">
                        Transfer <strong>Rp {{ number_format($order->total_amount, 0, ',', '.') }}</strong> ke:<br>
                        <strong>{{ $bank['bank'] }} {{ $bank['account_number'] }}</strong><br>
                        a.n. {{ $bank['account_name'] }}
                    </div>
                    <form method="POST" action="{{ route('orders.payment', $order->order_number) }}" enctype="multipart/form-data">
                        @csrf
                        <input type="file" name="payment_proof" accept=".jpg,.jpeg,.png,.webp,.pdf"
                               class="form-control mb-1 @error('payment_proof') is-invalid @enderror" aria-label="Bukti pembayaran">
                        @error('payment_proof') <div class="invalid-feedback d-block mb-1">{{ $message }}</div> @enderror
                        <div class="form-text mb-2">JPG, PNG, WEBP, atau PDF. Maksimal 3 MB.</div>
                        <button class="btn btn-rose w-100">Kirim bukti pembayaran</button>
                    </form>

                @elseif($order->payment_status === 'proof_uploaded' && $order->status === 'pending_payment')
                    <div class="alert alert-info small mb-2">Bukti diterima. Menunggu verifikasi admin.</div>
                    <a href="{{ route('orders.proof', $order->order_number) }}" target="_blank" class="small">Lihat bukti yang dikirim</a>

                @elseif($order->payment_status === 'confirmed')
                    <div class="alert alert-success small mb-2">
                        Pembayaran dikonfirmasi{{ $order->paid_at ? ' pada ' . $order->paid_at->format('d M Y H:i') : '' }}.
                    </div>
                    @if($order->payment_proof)
                        <a href="{{ route('orders.proof', $order->order_number) }}" target="_blank" class="small">Lihat bukti</a>
                    @endif
                @else
                    <div class="small text-muted">Tidak ada tindakan pembayaran.</div>
                @endif
            </div></div>

            @if($order->canBeCancelledByCustomer())
                <form method="POST" action="{{ route('orders.cancel', $order->order_number) }}"
                      onsubmit="return confirm('Batalkan pesanan ini?')">
                    @csrf
                    <button class="btn btn-outline-danger w-100">Batalkan pesanan</button>
                </form>
            @endif

            <a href="{{ route('orders.index') }}" class="btn btn-link w-100 mt-2">← Semua pesanan</a>
        </div>
    </div>

    {{-- FR-09: testimonial setelah pesanan diterima --}}
    @if($order->status === 'delivered')
        @php $testimonial = $order->testimonial; @endphp
        <div class="card border-0 shadow-sm mt-4"><div class="card-body">
            <h2 class="h6 section-title mb-3">Bagikan Pengalamanmu</h2>

            @if($testimonial)
                <div class="stars mb-1" aria-label="Rating {{ $testimonial->rating }} dari 5">
                    {!! str_repeat('★', $testimonial->rating) !!}<span class="off">{!! str_repeat('★', 5 - $testimonial->rating) !!}</span>
                </div>
                <p class="mb-2">“{{ $testimonial->content }}”</p>
                <span class="badge-soft {{ \App\Models\Testimonial::STATUS_BADGES[$testimonial->status()] }}">
                    {{ ['pending' => 'Menunggu persetujuan admin', 'approved' => 'Tampil di halaman Floré', 'rejected' => 'Tidak ditampilkan'][$testimonial->status()] }}
                </span>
            @else
                <form method="POST" action="{{ route('orders.testimonial', $order->order_number) }}" novalidate>
                    @csrf
                    <fieldset class="mb-3">
                        <legend class="form-label mb-1">Rating</legend>
                        <div class="star-input">
                            @for($s = 5; $s >= 1; $s--)
                                <input type="radio" id="star{{ $s }}" name="rating" value="{{ $s }}" @checked((int) old('rating') === $s)>
                                <label for="star{{ $s }}" title="{{ $s }} bintang">★<span class="visually-hidden">{{ $s }} bintang</span></label>
                            @endfor
                        </div>
                        @error('rating', 'testimonial') <div class="text-danger small">{{ $message }}</div> @enderror
                    </fieldset>
                    <div class="mb-3">
                        <label for="content" class="form-label">Ceritakan pengalamanmu</label>
                        <textarea id="content" name="content" rows="3" maxlength="500"
                                  class="form-control @error('content', 'testimonial') is-invalid @enderror"
                                  placeholder="Bunganya segar, rangkaiannya cantik…">{{ old('content') }}</textarea>
                        @error('content', 'testimonial') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <button class="btn btn-plum">Kirim Testimonial</button>
                </form>
            @endif
        </div></div>
    @endif
</div>
@endsection