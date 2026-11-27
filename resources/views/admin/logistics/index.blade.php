@extends('layouts.admin')
@section('title', 'Logistik & Kurir')

@section('content')
@php
    use App\Models\Courier;
    use App\Models\Order;

    $time = fn ($dt) => $dt?->format('H:i');
    $day = fn ($d) => $d->locale('id')->translatedFormat('D, d M');
    $state = $selected?->dispatchState();

    // --- peta rute skematik: posisi kurir dari progres ETA (bukan GPS) ---
    $route = [[60, 104], [158, 104], [158, 168], [338, 168]];
    $pointAt = function (float $p) use ($route) {
        $segs = [];
        $total = 0;
        for ($i = 1; $i < count($route); $i++) {
            $len = hypot($route[$i][0] - $route[$i - 1][0], $route[$i][1] - $route[$i - 1][1]);
            $segs[] = [$route[$i - 1], $route[$i], $len];
            $total += $len;
        }
        $left = $p * $total;
        foreach ($segs as [$a, $b, $len]) {
            if ($left <= $len) {
                $t = $len ? $left / $len : 0;
                return [$a[0] + ($b[0] - $a[0]) * $t, $a[1] + ($b[1] - $a[1]) * $t];
            }
            $left -= $len;
        }
        return end($route);
    };
    $progress = $selected?->estimatedProgress() ?? 0;
    [$kx, $ky] = $pointAt($progress);
    // titik-titik rute yang sudah dilalui (untuk garis solid)
    $donePts = [];
    $acc = 0; $totalLen = 0;
    foreach (array_slice($route, 1) as $i => $pt) { $totalLen += hypot($pt[0] - $route[$i][0], $pt[1] - $route[$i][1]); }
    $donePts[] = $route[0];
    foreach (array_slice($route, 1) as $i => $pt) {
        $acc += hypot($pt[0] - $route[$i][0], $pt[1] - $route[$i][1]);
        if ($acc <= $progress * $totalLen) { $donePts[] = $pt; }
    }
    $donePts[] = [$kx, $ky];
    $poly = fn ($pts) => collect($pts)->map(fn ($p) => round($p[0], 1) . ',' . round($p[1], 1))->implode(' ');

    $mapsUrl = $selected ? 'https://www.google.com/maps/dir/?api=1&travelmode=driving&origin=' . rawurlencode($studioAddress)
        . '&destination=' . rawurlencode($selected->delivery_address . ', ' . $selected->delivery_city) : null;
@endphp

<div class="admin-head d-flex flex-wrap justify-content-between align-items-end gap-2">
    <div>
        <h1 class="h3 mb-1">Logistics & Courier Dispatch Dashboard</h1>
        <p>Pantau armada kurir, antrean pengiriman, dan estimasi waktu tiba (ETA) pengantaran bunga.</p>
    </div>
    <div class="d-flex align-items-center gap-3">
        <span class="small text-muted"><span class="live-dot" aria-hidden="true"></span> Diperbarui {{ now()->format('H:i') }} · otomatis tiap 60 detik</span>
        <a href="{{ route('admin.couriers.index') }}" class="btn btn-outline-plum btn-sm">Kelola Kurir</a>
    </div>
</div>

@include('catalog._flash')

@if($couriers->isEmpty())
    <div class="alert alert-warning">
        Belum ada kurir aktif. <a href="{{ route('admin.couriers.index') }}" class="alert-link">Tambahkan kurir</a> agar pesanan bisa ditugaskan dan diberangkatkan.
    </div>
@endif

{{-- KPI --}}
<div class="row g-3 mb-4">
    <div class="col-6 col-xl-3">
        <div class="kpi">
            <div class="label">Siap Penugasan</div>
            <div class="value">{{ $kpi['ready'] }} Paket</div>
            <div class="note text-muted">{{ $kpi['ready_no_courier'] ? $kpi['ready_no_courier'] . ' belum ada kurir' : 'Sudah dirangkai di studio' }}</div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="kpi">
            <div class="label">Dalam Perjalanan</div>
            <div class="value text-success">{{ $kpi['active_couriers'] }} Kurir Aktif</div>
            <div class="note text-muted">{{ $kpi['on_way'] }} paket sedang diantar</div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="kpi">
            <div class="label">Terkirim Hari Ini</div>
            <div class="value">{{ $kpi['delivered_today'] }} Pesanan</div>
            <div class="note text-muted">{{ $kpi['on_time_pct'] === null ? 'Belum ada pengiriman selesai' : $kpi['on_time_pct'] . '% tepat waktu (sesuai slot)' }}</div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        @php $problems = $kpi['late'] + $kpi['issues']; @endphp
        <div class="kpi {{ $problems ? 'alert-kpi' : '' }}">
            <div class="label">Kendala / Delay</div>
            <div class="value {{ $problems ? '' : 'text-success' }}">{{ $problems }} Insiden</div>
            <div class="note text-muted">
                {{ $problems ? $kpi['late'] . ' melewati ETA · ' . $kpi['issues'] . ' kendala dilaporkan' : 'Semua pengiriman sesuai jadwal' }}
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    {{-- ======================= Antrean ======================= --}}
    <div class="col-lg-6">
        <div class="panel h-100">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                <h2 class="panel-title mb-0">Antrean Dispatch & Deliveries</h2>
                <nav class="nav report-tabs border-0" aria-label="Filter antrean">
                    <a class="nav-link {{ $tab === 'queue' ? 'active' : '' }}" href="{{ route('admin.logistics.index') }}">Antrean ({{ $counts['queue'] }})</a>
                    <a class="nav-link {{ $tab === 'delivered' ? 'active' : '' }}" href="{{ route('admin.logistics.index', ['tab' => 'delivered']) }}">Terkirim hari ini ({{ $counts['delivered'] }})</a>
                </nav>
            </div>

            @forelse($list as $o)
                @php [$label, $tone] = Order::DISPATCH_STATES[$o->dispatchState()]; @endphp
                <a href="{{ route('admin.logistics.index', array_filter(['order' => $o->id, 'tab' => $tab === 'delivered' ? 'delivered' : null])) }}"
                   class="dispatch-card {{ $selected?->id === $o->id ? 'selected' : '' }}" @if($selected?->id === $o->id) aria-current="true" @endif>
                    <div class="d-flex justify-content-between align-items-start gap-2">
                        <div class="fw-semibold">#{{ $o->order_number }}</div>
                        <span class="dispatch-badge {{ $tone }}">{{ $label }}</span>
                    </div>
                    <div class="d-flex justify-content-between gap-3 mt-1">
                        <div class="small">
                            <div>Tujuan: {{ \Illuminate\Support\Str::limit($o->delivery_address, 38) }}, {{ $o->delivery_city }}</div>
                            <div class="{{ $o->courier ? 'text-mauve' : 'text-danger' }} fw-medium">
                                Kurir: {{ $o->courier ? $o->courier->name . ' (' . $o->courier->vehicleLabel() . ')' : 'Belum ditugaskan' }}
                            </div>
                        </div>
                        <div class="small text-end text-nowrap fw-semibold">
                            @if($o->status === 'out_for_delivery')
                                ETA: {{ $time($o->eta_at) ?? '—' }}
                            @elseif($o->status === 'delivered')
                                Tiba: {{ $time($o->delivered_at) }}
                            @else
                                Jadwal: {{ $day($o->delivery_date) }}
                            @endif
                            <div class="fw-normal text-muted">{{ $slots[$o->delivery_slot] ?? '' }}</div>
                        </div>
                    </div>
                    <div class="meta">
                        Penerima: {{ $o->recipient_name }} · {{ $o->items_count }} item
                        @if($o->status === 'preparing' && $o->stageLabel()) · Studio: <span class="{{ $o->fulfillment_stage === 'ready' ? 'text-success fw-semibold' : '' }}">{{ $o->stageLabel() }}</span> @endif
                        @if($o->hasOpenIssue()) <span class="text-danger fw-semibold">· Kendala: {{ \Illuminate\Support\Str::limit($o->delivery_issue, 40) }}</span> @endif
                    </div>
                </a>
            @empty
                <div class="text-center text-muted py-5 small">
                    {{ $tab === 'delivered' ? 'Belum ada pesanan yang terkirim hari ini.' : 'Tidak ada pesanan yang perlu dikirim. Pesanan muncul di sini setelah pembayarannya dikonfirmasi.' }}
                </div>
            @endforelse
        </div>
    </div>

    {{-- ======================= Rute & monitoring ======================= --}}
    <div class="col-lg-6">
        <div class="panel h-100">
            @if(! $selected)
                <div class="text-center text-muted py-5 small">Pilih pesanan di antrean untuk melihat rute dan detail kurir.</div>
            @else
                {{-- peta rute skematik --}}
                <figure class="route-map mb-2" aria-label="Estimasi posisi kurir">
                    <svg viewBox="0 0 400 230" role="img" aria-labelledby="route-title">
                        <title id="route-title">Rute dari Floré Studio Hub ke {{ $selected->delivery_city }}, progres {{ round($progress * 100) }} persen</title>
                        <rect width="400" height="230" fill="#e9e7e2"/>
                        <rect x="0" y="95" width="400" height="18" fill="#fff"/>
                        <rect x="150" y="0" width="16" height="230" fill="#fff"/>
                        <rect x="0" y="162" width="400" height="12" fill="#fff"/>
                        <rect x="300" y="0" width="12" height="230" fill="#fff"/>
                        <polyline points="{{ $poly($route) }}" fill="none" stroke="#c9a3ad" stroke-width="3" stroke-dasharray="6 5"/>
                        <polyline points="{{ $poly($donePts) }}" fill="none" stroke="#4a2a30" stroke-width="3.5" stroke-linecap="round"/>

                        <circle cx="60" cy="104" r="11" fill="#4a2a30"/>
                        <text x="60" y="108" text-anchor="middle" font-size="10" font-weight="700" fill="#fff">H</text>
                        <text x="60" y="84" text-anchor="middle" font-size="10" font-weight="600" fill="#4a2a30">Floré Studio Hub</text>

                        <circle cx="338" cy="168" r="11" fill="#c4524a"/>
                        <text x="338" y="172" text-anchor="middle" font-size="10" font-weight="700" fill="#fff">D</text>
                        <text x="338" y="148" text-anchor="end" font-size="10" font-weight="600" fill="#c4524a">{{ \Illuminate\Support\Str::limit($selected->delivery_city, 22) }}</text>

                        @if($selected->courier)
                            <circle cx="{{ round($kx, 1) }}" cy="{{ round($ky, 1) }}" r="9" fill="#6b8f71" stroke="#fff" stroke-width="2"/>
                            <text x="{{ round($kx, 1) }}" y="{{ round($ky + 3.5, 1) }}" text-anchor="middle" font-size="9" font-weight="700" fill="#fff">K</text>
                            <text x="{{ round(min(max($kx, 40), 300), 1) }}" y="{{ round($ky - 14, 1) }}" text-anchor="middle" font-size="9" fill="#4a7151">{{ \Illuminate\Support\Str::limit($selected->courier->name, 18) }}</text>
                        @endif
                    </svg>
                    <figcaption class="d-flex flex-wrap justify-content-between gap-2 small">
                        <span>
                            <span class="live-dot" aria-hidden="true"></span>
                            @switch($state)
                                @case('on_the_way') Estimasi posisi dari ETA ({{ round($progress * 100) }}%) · bukan GPS live @break
                                @case('late') Sudah melewati ETA {{ $time($selected->eta_at) }} — hubungi kurir @break
                                @case('delivered') Terkirim pukul {{ $time($selected->delivered_at) }} @break
                                @default Belum berangkat dari studio
                            @endswitch
                        </span>
                        <a href="{{ $mapsUrl }}" target="_blank" rel="noopener">Buka rute di Google Maps ↗</a>
                    </figcaption>
                </figure>

                <h2 class="panel-title mt-3 mb-2">
                    @if($selected->courier)
                        Monitoring Kurir: {{ $selected->courier->name }} (#{{ $selected->order_number }})
                    @else
                        Pesanan #{{ $selected->order_number }}
                    @endif
                </h2>

                <ul class="monitor-list">
                    @if($selected->courier)
                        <li><span>Armada</span> {{ $selected->courier->vehicleLabel() }}{{ $selected->courier->plate_number ? ' · ' . $selected->courier->plate_number : '' }}</li>
                        <li><span>Kontak kurir</span> <a href="tel:{{ $selected->courier->phone }}">{{ $selected->courier->phone }}</a></li>
                    @endif
                    <li><span>Penerima</span> {{ $selected->recipient_name }} · <a href="tel:{{ $selected->recipient_phone }}">{{ $selected->recipient_phone }}</a></li>
                    <li><span>Alamat</span> {{ $selected->delivery_address }}, {{ $selected->delivery_city }}</li>
                    <li><span>Jadwal</span> {{ $selected->delivery_date->locale('id')->translatedFormat('l, d M Y') }} · {{ $slots[$selected->delivery_slot] ?? '-' }}</li>
                    <li><span>Isi paket</span> {{ $selected->items->map(fn ($i) => $i->quantity . '× ' . $i->name)->implode(', ') }}</li>
                    @if($selected->card_message)
                        <li><span>Kartu ucapan</span> “{{ $selected->card_message }}”</li>
                    @endif
                    @if($selected->dispatched_at)
                        <li><span>Berangkat</span> {{ $time($selected->dispatched_at) }}
                            @if($selected->eta_at && $selected->status === 'out_for_delivery')
                                · ETA {{ $time($selected->eta_at) }}
                                <strong class="{{ $selected->isLate() ? 'text-danger' : 'text-success' }}">
                                    ({{ $selected->isLate() ? 'terlambat ' . (int) $selected->eta_at->diffInMinutes(now()) . ' menit' : 'sisa ' . max(1, (int) ceil(now()->diffInMinutes($selected->eta_at))) . ' menit' }})
                                </strong>
                            @endif
                        </li>
                    @elseif($selected->courier && $selected->travel_minutes)
                        <li><span>Estimasi tempuh</span> ± {{ $selected->travel_minutes }} menit setelah berangkat</li>
                    @endif
                    @if($selected->hasOpenIssue())
                        <li class="issue"><span>Kendala</span> {{ $selected->delivery_issue }} <small>({{ $time($selected->issue_reported_at) }})</small></li>
                    @endif
                    @if($selected->status === 'delivered')
                        <li><span>Diterima oleh</span> {{ $selected->received_by ?? '—' }} · {{ $selected->delivered_at?->format('d M Y H:i') }}
                            @if($selected->deliveredOnTime() !== null)
                                <strong class="{{ $selected->deliveredOnTime() ? 'text-success' : 'text-danger' }}">({{ $selected->deliveredOnTime() ? 'tepat waktu' : 'lewat slot' }})</strong>
                            @endif
                        </li>
                        @if($selected->delivery_photo)
                            <li><span>Foto serah terima</span> <a href="{{ route('admin.logistics.photo', $selected) }}" target="_blank">Lihat foto POD</a></li>
                        @endif
                    @endif
                </ul>

                {{-- ---------- aksi sesuai tahap ---------- --}}

                @if(in_array($state, ['not_prepared', 'waiting_courier', 'ready_pickup'], true))
                    @if($state === 'not_prepared')
                        <div class="small text-muted mb-2">Pembayaran sudah dikonfirmasi, bouquet belum mulai dirangkai. Kurir boleh ditugaskan lebih awal.</div>
                    @endif

                    @include('admin.logistics._courier-form', ['route' => route('admin.logistics.assign', $selected), 'button' => $selected->courier ? 'Ganti Kurir' : 'Tugaskan Kurir', 'o' => $selected, 'minutesFromNow' => false])

                    @if($state === 'not_prepared')
                        <form method="POST" action="{{ route('admin.orders.advance', $selected) }}" class="mt-2">
                            @csrf <button class="btn btn-outline-plum w-100">Mulai Rangkai → "Sedang disiapkan"</button>
                        </form>
                    @elseif($state === 'ready_pickup')
                        <form method="POST" action="{{ route('admin.logistics.dispatch', $selected) }}" class="mt-2">
                            @csrf <button class="btn btn-plum w-100 py-2">Berangkatkan — Serahkan ke Kurir</button>
                        </form>
                    @endif
                @elseif(in_array($state, ['on_the_way', 'late'], true))
                    <a href="{{ $selected->courier?->whatsappUrl("Halo {$selected->courier?->name}, bagaimana update pengiriman #{$selected->order_number} ke {$selected->recipient_name}?") }}"
                       target="_blank" rel="noopener" class="btn btn-outline-plum w-100 mb-2 @if(! $selected->courier) disabled @endif">Hubungi Kurir (WhatsApp)</a>

                    <details class="action-box mb-2">
                        <summary>Re-route / ganti kurir & perbarui ETA</summary>
                        @include('admin.logistics._courier-form', ['route' => route('admin.logistics.reroute', $selected), 'button' => 'Simpan Re-route', 'o' => $selected, 'minutesFromNow' => true])
                    </details>

                    @if($selected->hasOpenIssue())
                        <form method="POST" action="{{ route('admin.logistics.issue.resolve', $selected) }}" class="mb-2">
                            @csrf <button class="btn btn-outline-secondary w-100">Tandai Kendala Selesai</button>
                        </form>
                    @else
                        <details class="action-box mb-2">
                            <summary>Laporkan kendala / delay</summary>
                            <form method="POST" action="{{ route('admin.logistics.issue', $selected) }}" class="mt-2">
                                @csrf
                                <label for="delivery_issue" class="form-label small">Catatan kendala</label>
                                <input id="delivery_issue" name="delivery_issue" maxlength="255" required class="form-control form-control-sm mb-2"
                                       placeholder="mis. Macet di Jl. Sudirman, penerima belum bisa dihubungi">
                                <button class="btn btn-sm btn-outline-danger">Catat Kendala</button>
                            </form>
                        </details>
                    @endif

                    <form method="POST" action="{{ route('admin.logistics.deliver', $selected) }}" enctype="multipart/form-data" class="action-box solid">
                        @csrf
                        <div class="fw-semibold small mb-2">Konfirmasi Terkirim (Proof of Delivery)</div>
                        <label for="received_by" class="form-label small mb-1">Diterima oleh</label>
                        <input id="received_by" name="received_by" maxlength="80" required class="form-control form-control-sm mb-2 @error('received_by') is-invalid @enderror"
                               value="{{ old('received_by', $selected->recipient_name) }}">
                        @error('received_by') <div class="invalid-feedback d-block mb-2">{{ $message }}</div> @enderror
                        <label for="delivery_photo" class="form-label small mb-1">Foto serah terima <span class="text-muted">(opsional, JPG/PNG/WEBP maks. 5 MB)</span></label>
                        <input id="delivery_photo" type="file" name="delivery_photo" accept="image/jpeg,image/png,image/webp"
                               class="form-control form-control-sm mb-2 @error('delivery_photo') is-invalid @enderror">
                        @error('delivery_photo') <div class="invalid-feedback d-block mb-2">{{ $message }}</div> @enderror
                        <button class="btn btn-plum w-100 py-2">Konfirmasi Terkirim (POD)</button>
                    </form>
                @endif

                <a href="{{ route('admin.orders.show', $selected) }}" class="btn btn-link w-100 mt-2 small">Detail pesanan lengkap →</a>
            @endif
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    // Segarkan otomatis supaya ETA & status selalu terbaru — kecuali admin sedang mengisi form.
    (() => {
        let dirty = false;
        document.addEventListener('input', () => { dirty = true; });
        setInterval(() => {
            const busy = dirty || document.querySelector('details[open]')
                || ['INPUT', 'SELECT', 'TEXTAREA'].includes(document.activeElement?.tagName);
            if (!busy && document.visibilityState === 'visible') location.reload();
        }, 60000);
    })();
</script>
@endpush
