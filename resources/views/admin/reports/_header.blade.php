{{-- Judul + tab modul laporan + (opsional) filter rentang tanggal & export --}}
<div class="admin-head">
    <h1 class="h3 mb-1">{{ $title }}</h1>
    <p>{{ $subtitle }}</p>
</div>

<ul class="nav report-tabs mb-3">
    @foreach([
        'admin.reports.sales' => 'Sales Report',
        'admin.reports.products' => 'Best-selling Products',
        'admin.reports.customers' => 'Customer Data',
        'admin.testimonials.index' => 'Testimonial',
    ] as $route => $text)
        <li class="nav-item">
            <a href="{{ route($route) }}" class="nav-link {{ request()->routeIs($route) ? 'active' : '' }}">{{ $text }}</a>
        </li>
    @endforeach
</ul>

@isset($from)
    <div class="panel py-3 mb-4 d-flex flex-wrap gap-2 align-items-end justify-content-between">
        <div class="d-flex flex-wrap gap-1">
            @foreach($presets as $key => $text)
                <a href="{{ request()->fullUrlWithQuery(['preset' => $key, 'from' => null, 'to' => null, 'page' => null]) }}"
                   class="btn btn-sm {{ $preset === $key ? 'btn-plum' : 'btn-outline-plum' }}">{{ $text }}</a>
            @endforeach
        </div>
        <form method="GET" class="d-flex flex-wrap gap-2 align-items-end">
            <div>
                <label for="from" class="form-label mb-0">Dari</label>
                <input type="date" id="from" name="from" value="{{ $from->toDateString() }}" max="{{ today()->toDateString() }}" class="form-control form-control-sm">
            </div>
            <div>
                <label for="to" class="form-label mb-0">Sampai</label>
                <input type="date" id="to" name="to" value="{{ $to->toDateString() }}" max="{{ today()->toDateString() }}" class="form-control form-control-sm">
            </div>
            <button class="btn btn-sm btn-plum">Terapkan</button>
            <a href="{{ request()->fullUrlWithQuery(['export' => 'csv', 'page' => null]) }}" class="btn btn-sm btn-outline-secondary">Ekspor CSV</a>
        </form>
    </div>
    <p class="small text-muted mb-3">
        Periode: <strong>{{ $from->locale('id')->translatedFormat('d M Y') }} – {{ $to->locale('id')->translatedFormat('d M Y') }}</strong>
        · dihitung dari pesanan yang pembayarannya sudah dikonfirmasi (tidak termasuk yang dibatalkan).
    </p>
@endisset
