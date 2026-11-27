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
                                <td class="text-end text-nowrap">Rp {{ number_format($item->price, 0, ',', '.') }}</td>
                                <td class="text-center">{{ $item->quantity }}</td>
                                <td class="text-end text-nowrap">Rp {{ number_format($item->subtotal, 0, ',', '.') }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                        <tfoot>
                            <tr><th colspan="3" class="text-end">Total</th><th class="text-end">Rp {{ number_format($order->total_amount, 0, ',', '.') }}</th></tr>
                            @if($order->items->every(fn ($i) => $i->unit_cost !== null))
                                @php $profit = $order->items->sum(fn ($i) => $i->grossProfit()); $sub = (float) $order->items->sum('subtotal'); @endphp
                                <tr class="small">
                                    <td colspan="3" class="text-end text-muted">Laba kotor (subtotal − HPP)</td>
                                    <td class="text-end fw-semibold {{ $profit < 0 ? 'text-danger' : 'text-success' }}">
                                        Rp {{ number_format($profit, 0, ',', '.') }}{{ $sub > 0 ? ' · ' . round($profit / $sub * 100, 1) . '%' : '' }}
                                    </td>
                                </tr>
                            @endif
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

            {{-- Produksi di studio (mockup hal. 48) --}}
            @if(in_array($order->status, ['payment_confirmed', 'preparing', 'out_for_delivery', 'delivered'], true))
                @php
                    $stageKeys = array_keys(Order::STAGES);
                    $stageIdx = array_search($order->fulfillment_stage, $stageKeys, true);
                    $done = in_array($order->status, ['out_for_delivery', 'delivered'], true);
                @endphp
                <div class="card border-0 shadow-sm mb-4"><div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h2 class="h6 mb-0">Produksi & Fulfillment</h2>
                        <a href="{{ route('admin.orders.print', $order) }}" target="_blank" class="btn btn-sm btn-outline-plum">Cetak Label & Kartu</a>
                    </div>

                    <div class="small text-muted mb-1">Florist</div>
                    @if(in_array($order->status, ['payment_confirmed', 'preparing'], true))
                        <form method="POST" action="{{ route('admin.orders.florist', $order) }}" class="mb-3">
                            @csrf
                            <select name="florist_id" class="form-select form-select-sm mb-1" aria-label="Florist" required @disabled($florists->isEmpty())>
                                <option value="">— Pilih florist —</option>
                                @foreach($florists as $f)
                                    <option value="{{ $f->id }}" @selected($order->florist_id === $f->id)>{{ $f->name }}{{ $f->specialty ? ' · ' . $f->specialty : '' }}</option>
                                @endforeach
                            </select>
                            <input name="production_note" maxlength="255" value="{{ $order->production_note }}" class="form-control form-control-sm mb-1"
                                   placeholder="Catatan produksi, mis. tambahkan kartu emas" aria-label="Catatan produksi">
                            @if($florists->isEmpty())
                                <div class="small text-muted">Belum ada florist. <a href="{{ route('admin.florists.index') }}">Tambah florist</a></div>
                            @else
                                <button class="btn btn-sm btn-outline-plum w-100">{{ $order->florist ? 'Ganti Florist' : 'Tugaskan Florist' }}</button>
                            @endif
                        </form>
                    @else
                        <div class="small mb-3">{{ $order->florist?->name ?? '—' }}</div>
                    @endif

                    <ol class="stage-list">
                        @foreach(Order::STAGES as $key => $label)
                            @php $i = $loop->index; $state = $done || ($stageIdx !== false && $i < $stageIdx) || ($stageIdx !== false && $i === $stageIdx && $key === 'ready') ? 'done' : ($stageIdx === $i ? 'current' : ''); @endphp
                            <li class="{{ $state }}">{{ $i + 1 }}. {{ $label }}
                                @if($key === 'qc' && $order->qc_passed_at) <span class="small text-success">· lulus {{ $order->qc_passed_at->format('d M H:i') }}</span> @endif
                            </li>
                        @endforeach
                    </ol>

                    @if($order->qc_photo)
                        <a href="{{ route('admin.orders.qc-photo', $order) }}" target="_blank">
                            <img src="{{ route('admin.orders.qc-photo', $order) }}" alt="Foto hasil rangkaian" class="img-fluid rounded mb-2" style="max-height: 220px">
                        </a>
                    @endif

                    @if($order->status === 'payment_confirmed')
                        <form method="POST" action="{{ route('admin.orders.stage', $order) }}">
                            @csrf <button class="btn btn-plum btn-sm w-100">Mulai Potong & Rangkai</button>
                        </form>
                    @elseif($next = $order->nextStage())
                        <form method="POST" action="{{ route('admin.orders.stage', $order) }}" enctype="multipart/form-data">
                            @csrf
                            @if($order->fulfillment_stage === 'qc')
                                <label for="qc_photo" class="form-label small mb-1">Foto hasil rangkaian {{ $order->qc_photo ? '(ganti, opsional)' : '(wajib)' }}</label>
                                <input id="qc_photo" type="file" name="qc_photo" accept="image/jpeg,image/png,image/webp" class="form-control form-control-sm mb-2 @error('qc_photo') is-invalid @enderror" @unless($order->qc_photo) required @endunless>
                                @error('qc_photo') <div class="invalid-feedback d-block mb-2">{{ $message }}</div> @enderror
                                <button class="btn btn-plum btn-sm w-100">Lulus QC → {{ Order::STAGES[$next] }}</button>
                            @else
                                <button class="btn btn-plum btn-sm w-100">Lanjut: {{ Order::STAGES[$next] }}</button>
                            @endif
                        </form>
                    @elseif($order->status === 'preparing' && $order->fulfillment_stage === 'ready')
                        <div class="small text-success">Siap diserahkan ke kurir — berangkatkan dari halaman <a href="{{ route('admin.logistics.index', ['order' => $order->id]) }}">Logistik & Kurir</a>.</div>
                    @endif
                </div></div>
            @endif

            @if(in_array($order->status, ['payment_confirmed', 'preparing', 'out_for_delivery', 'delivered'], true))
                <div class="card border-0 shadow-sm mb-4"><div class="card-body">
                    <h2 class="h6 mb-2">Logistik</h2>
                    @if($order->courier)
                        <div class="small">Kurir: <strong>{{ $order->courier->name }}</strong> · {{ $order->courier->vehicleLabel() }}</div>
                        @if($order->dispatched_at) <div class="small">Berangkat {{ $order->dispatched_at->format('d M H:i') }}{{ $order->eta_at && $order->status === 'out_for_delivery' ? ' · ETA ' . $order->eta_at->format('H:i') : '' }}</div> @endif
                        @if($order->delivered_at) <div class="small">Diterima {{ $order->received_by ?? '—' }} · {{ $order->delivered_at->format('d M H:i') }}</div> @endif
                    @else
                        <div class="small text-muted">Belum ada kurir yang ditugaskan.</div>
                    @endif
                    <a href="{{ route('admin.logistics.index', ['order' => $order->id]) }}" class="btn btn-sm btn-outline-plum w-100 mt-2">Buka di Logistik & Kurir</a>
                </div></div>
            @endif

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
                <a href="{{ route('admin.orders.index') }}" class="btn btn-link w-100 mt-2">← Order Management</a>
            </div></div>
        </div>
    </div>
</div>
@endsection