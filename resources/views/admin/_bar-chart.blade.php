{{--
    Grafik batang satu seri (pendapatan per hari). Tanpa library: HTML + CSS.
    Pakai: @include('admin._bar-chart', ['series' => $series, 'label' => 'Pendapatan harian'])
    $series: Collection [{date: Carbon, revenue: float, orders: int}]
--}}
@php
    $max = max(1, (float) $series->max('revenue'));
    // garis bantu: 0, 50%, 100% dari nilai "bulat" terdekat di atas maksimum
    $magnitude = 10 ** max(0, strlen((string) (int) $max) - 1);
    $top = ceil($max / $magnitude) * $magnitude;
    $short = fn ($v) => $v >= 1_000_000 ? rtrim(rtrim(number_format($v / 1_000_000, 1, ',', '.'), '0'), ',') . ' jt'
        : ($v >= 1_000 ? number_format($v / 1_000, 0, ',', '.') . ' rb' : number_format($v, 0, ',', '.'));
    $rp = fn ($v) => 'Rp ' . number_format($v, 0, ',', '.');
    $every = max(1, (int) ceil($series->count() / 10));   // label tanggal tidak berdesakan
    $chartId = 'chart-' . \Illuminate\Support\Str::random(6);
@endphp

<figure class="bar-chart mb-0" aria-labelledby="{{ $chartId }}-cap">
    <figcaption id="{{ $chartId }}-cap" class="visually-hidden">{{ $label }}</figcaption>
    <div class="bar-chart-plot">
        <div class="bar-chart-grid" aria-hidden="true">
            @foreach([1, 0.5, 0] as $f)
                <div class="gridline"><span>{{ $short($top * $f) }}</span></div>
            @endforeach
        </div>
        <div class="bar-chart-bars">
            @foreach($series as $i => $p)
                <div class="bar-col" tabindex="0"
                     aria-label="{{ $p['date']->locale('id')->translatedFormat('d F') }}: {{ $rp($p['revenue']) }}, {{ $p['orders'] }} pesanan">
                    <div class="bar" style="height: {{ $p['revenue'] > 0 ? max(1.5, $p['revenue'] / $top * 100) : 0 }}%"></div>
                    <div class="bar-tip" role="tooltip">
                        <strong>{{ $p['date']->locale('id')->translatedFormat('D, d M') }}</strong><br>
                        {{ $rp($p['revenue']) }}<br>
                        <span>{{ $p['orders'] }} pesanan lunas</span>
                    </div>
                    <div class="bar-x">{{ $i % $every === 0 ? $p['date']->format('d/m') : '' }}</div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- tampilan tabel untuk pembaca layar --}}
    <table class="visually-hidden">
        <caption>{{ $label }}</caption>
        <thead><tr><th>Tanggal</th><th>Pendapatan</th><th>Pesanan</th></tr></thead>
        <tbody>
        @foreach($series as $p)
            <tr><td>{{ $p['date']->format('d/m/Y') }}</td><td>{{ $rp($p['revenue']) }}</td><td>{{ $p['orders'] }}</td></tr>
        @endforeach
        </tbody>
    </table>
</figure>
