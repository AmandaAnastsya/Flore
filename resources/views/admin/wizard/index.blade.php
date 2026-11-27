@extends('layouts.admin')
@section('title', 'Smart Wizard & AI Insights')

@section('content')
@php
    use App\Services\RecommendationEngine;
    use App\Services\WizardWeights;

    $label = fn ($v) => ucwords(str_replace('-', ' ', (string) $v));
    $rp = fn ($v) => 'Rp ' . number_format($v, 0, ',', '.');
    $swatch = ['pink' => '#e3a3b2', 'white' => '#f4efe8', 'lavender' => '#b7a5d4', 'peach' => '#f0b896', 'red' => '#a8394a'];
    $budgetLabels = array_map(fn ($b) => $b['label'], RecommendationEngine::BUDGETS);
    $criteriaLabel = ['occasion' => 'Momen', 'color' => 'Warna', 'style' => 'Gaya', 'size' => 'Ukuran', 'budget' => 'Budget'];
@endphp

<div class="admin-head d-flex flex-wrap justify-content-between align-items-end gap-2">
    <div>
        <h1 class="h3 mb-1">Smart Wizard & AI Insights</h1>
        <p>Analisis perilaku konsumen dari data Smart Wizard dan pengaturan bobot algoritma rekomendasi secara langsung.</p>
    </div>
    <nav class="nav report-tabs border-0" aria-label="Periode">
        @foreach(\App\Services\WizardInsights::PERIODS as $d => $text)
            <a href="{{ route('admin.wizard.index', ['days' => $d]) }}" class="nav-link {{ $days === $d ? 'active' : '' }}">{{ $text }}</a>
        @endforeach
    </nav>
</div>

@include('catalog._flash')

{{-- KPI --}}
<div class="row g-3 mb-4">
    <div class="col-6 col-xl-3">
        <div class="kpi accent">
            <div class="label">Pencarian Wizard</div>
            <div class="value">{{ $summary['total'] }} Queries</div>
            <div class="note">
                @if($summary['change'] === null) <span class="text-muted">belum ada data periode lalu</span>
                @else <span class="{{ $summary['change'] < 0 ? 'text-danger' : 'text-success' }}">{{ $summary['change'] >= 0 ? '+' : '' }}{{ $summary['change'] }}%</span> <span class="text-muted">vs {{ $days }} hari sebelumnya</span>
                @endif
            </div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="kpi">
            <div class="label">Customer Unik</div>
            <div class="value">{{ $summary['users'] }} Orang</div>
            <div class="note text-muted">yang login saat memakai wizard</div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="kpi">
            <div class="label">Konversi ke Pesanan</div>
            <div class="value">{{ $summary['conversion'] === null ? '—' : $summary['conversion'] . '%' }}</div>
            <div class="note text-muted">customer wizard yang lalu memesan</div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="kpi {{ $summary['exact_rate'] !== null && $summary['exact_rate'] < 60 ? 'alert-kpi' : '' }}">
            <div class="label">Cocok Persis</div>
            <div class="value">{{ $summary['exact_rate'] === null ? '—' : $summary['exact_rate'] . '%' }}</div>
            <div class="note text-muted">{{ $summary['gaps'] }} pencarian tanpa hasil persis</div>
        </div>
    </div>
</div>

{{-- ============ Section 1 ============ --}}
<div class="panel mb-4">
    <h2 class="panel-title">Section 1: Behavioral Insights & Customer Preferences</h2>

    <div class="row g-3">
        <div class="col-lg-4">
            <div class="insight-box h-100">
                <h3>Tren Warna Favorit</h3>
                <p class="hint">Berdasarkan pilihan di wizard</p>
                @forelse($colors as $c)
                    <div class="mb-3">
                        <div class="d-flex justify-content-between small mb-1">
                            <span><span class="swatch-dot" style="background: {{ $swatch[$c['value']] ?? '#d9c2c8' }}"></span>{{ $label($c['value']) }}</span>
                            <strong>{{ $c['percent'] }}%</strong>
                        </div>
                        <div class="bar"><span style="width: {{ $c['percent'] }}%; background: {{ $swatch[$c['value']] ?? '#8e5a66' }}"></span></div>
                    </div>
                @empty
                    <p class="text-muted small mb-0">Belum ada pencarian dengan pilihan warna.</p>
                @endforelse
            </div>
        </div>

        <div class="col-lg-4">
            <div class="insight-box h-100">
                <h3>Kombinasi Bunga Populer</h3>
                <p class="hint">Bouquet yang paling sering direkomendasikan</p>
                @forelse($combos as $i => $c)
                    <div class="combo-card">
                        <div class="fw-semibold small">{{ $i + 1 }}. {{ $c['name'] }}</div>
                        @if($c['flowers']) <div class="text-muted" style="font-size:.72rem">{{ $c['flowers'] }}</div> @endif
                        <div class="text-success fw-semibold" style="font-size:.72rem">● {{ $c['recommended'] }} rekomendasi · {{ $c['ordered'] }} dipesan</div>
                    </div>
                @empty
                    <p class="text-muted small mb-0">Data muncul setelah customer memakai wizard.</p>
                @endforelse
            </div>
        </div>

        <div class="col-lg-4">
            <div class="insight-box h-100">
                <h3>Rata-Rata Budget per Momen</h3>
                <p class="hint">Nilai pesanan lunas per occasion</p>
                @forelse($budgets as $b)
                    <div class="budget-row">
                        <div class="d-flex justify-content-between align-items-start gap-2">
                            <div>
                                <div class="fw-semibold small">{{ $label($b['occasion']) }}</div>
                                <div class="fw-bold" style="font-size:.85rem">{{ $rp($b['avg']) }}</div>
                            </div>
                            @if($b['change'] !== null)
                                <span class="small fw-semibold {{ $b['change'] < 0 ? 'text-danger' : 'text-success' }}">{{ $b['change'] >= 0 ? '+' : '' }}{{ $b['change'] }}%</span>
                            @else
                                <span class="small text-muted">baru</span>
                            @endif
                        </div>
                        <div class="text-muted" style="font-size:.7rem">{{ $b['orders'] }} pesanan{{ $b['wizard_budget'] ? ' · budget wizard terbanyak: ' . $b['wizard_budget'] : '' }}</div>
                    </div>
                @empty
                    <p class="text-muted small mb-0">Belum ada pesanan lunas di periode ini.</p>
                @endforelse
            </div>
        </div>
    </div>

    <div class="row g-3 mt-1">
        <div class="col-lg-6">
            <div class="insight-box h-100">
                <h3>Insight Otomatis</h3>
                <p class="hint">Disusun dari aturan sederhana atas data wizard & pesanan (bukan model machine learning)</p>
                @if($highlights)
                    <ul class="insight-list">
                        @foreach($highlights as $line) <li>{{ $line }}</li> @endforeach
                    </ul>
                @else
                    <p class="text-muted small mb-0">Belum cukup data untuk disimpulkan.</p>
                @endif
            </div>
        </div>
        <div class="col-lg-6">
            <div class="insight-box h-100">
                <h3>Permintaan Belum Terpenuhi</h3>
                <p class="hint">Pilihan customer yang tidak menemukan bouquet yang cocok persis</p>
                @forelse($gaps as $g)
                    <div class="d-flex justify-content-between gap-2 small py-1 border-bottom">
                        <span>
                            @foreach($g['criteria'] as $k => $v)
                                <span class="badge-soft primary me-1">{{ $criteriaLabel[$k] ?? $k }}: {{ $k === 'budget' ? ($budgetLabels[$v] ?? $v) : $label($v) }}</span>
                            @endforeach
                        </span>
                        <strong class="text-nowrap">{{ $g['total'] }}×</strong>
                    </div>
                @empty
                    <p class="text-muted small mb-0">Semua pencarian menemukan bouquet yang cocok. 🎉</p>
                @endforelse
            </div>
        </div>
    </div>
</div>

{{-- ============ Section 2 ============ --}}
<div class="panel mb-4">
    <h2 class="panel-title mb-1">Section 2: Rule Engine Configurator (No-Code Algorithm Weights)</h2>
    <p class="small text-muted mb-3">
        Ubah bobot penilaian algoritma Smart Wizard. Perubahan langsung berdampak pada urutan & persentase kecocokan rekomendasi.
        Status saat ini: <strong>{{ $customWeights ? 'bobot kustom' : 'bobot bawaan' }}</strong>.
    </p>

    @error('weights') <div class="alert alert-danger py-2">{{ $message }}</div> @enderror

    <form method="POST" action="{{ route('admin.wizard.weights') }}" data-weights-form>
        @csrf @method('PUT')
        @foreach(WizardWeights::FACTORS as $key => [$title, $sub, $desc])
            @php $value = old("weights.$key", $weights[$key]); @endphp
            <div class="weight-row">
                <div class="flex-grow-1">
                    <label for="w-{{ $key }}" class="fw-semibold small mb-0">{{ $title }} <span class="text-muted fw-normal">({{ $sub }})</span></label>
                    <div class="text-muted" style="font-size:.72rem">{{ $desc }}</div>
                    <input id="w-{{ $key }}" type="range" min="{{ WizardWeights::MIN[$key] }}" max="80" step="1" value="{{ $value }}"
                           class="form-range weight-range" data-target="n-{{ $key }}" aria-describedby="n-{{ $key }}">
                </div>
                <div class="weight-num">
                    <input id="n-{{ $key }}" type="number" name="weights[{{ $key }}]" min="{{ WizardWeights::MIN[$key] }}" max="80" value="{{ $value }}"
                           class="form-control form-control-sm text-center @error("weights.$key") is-invalid @enderror" aria-label="{{ $title }} (persen)">
                    <span>%</span>
                </div>
            </div>
            @error("weights.$key") <div class="small text-danger mb-2">{{ $message }}</div> @enderror
        @endforeach

        <div class="weight-footer">
            <span class="small fw-semibold" data-weight-status>
                <span class="live-dot" aria-hidden="true"></span>
                Status Algoritma: Live & Active (Total Bobot: <span data-weight-total>100</span>%)
            </span>
            <div class="d-flex gap-2">
                <button type="submit" form="reset-weights" class="btn btn-sm btn-outline-secondary">Reset Default</button>
                <button class="btn btn-sm btn-plum" data-weight-save>Simpan & Terapkan</button>
            </div>
        </div>
        <p class="small text-muted mt-2 mb-0">
            Faktor <em>Optimasi Profit Margin</em> memakai harga modal (HPP) di Inventaris — bouquet tanpa HPP mendapat nilai 0. Margin tidak pernah ditampilkan ke customer.
        </p>
    </form>
    <form id="reset-weights" method="POST" action="{{ route('admin.wizard.weights.reset') }}" onsubmit="return confirm('Kembalikan bobot ke pengaturan bawaan?')">@csrf</form>
</div>

{{-- ============ Section 3: simulasi ============ --}}
<div class="panel">
    <h2 class="panel-title mb-1">Uji Coba Rekomendasi</h2>
    <p class="small text-muted mb-3">Lihat hasil wizard dengan bobot yang sedang aktif. Simulasi tidak dihitung sebagai pencarian customer.</p>

    <form method="GET" action="{{ route('admin.wizard.index') }}" class="row g-2 align-items-end mb-3">
        <input type="hidden" name="days" value="{{ $days }}">
        @foreach(RecommendationEngine::CRITERIA as $c)
            <div class="col-6 col-md">
                <label for="sim-{{ $c }}" class="form-label small mb-1">{{ $criteriaLabel[$c] }}</label>
                <select id="sim-{{ $c }}" name="sim[{{ $c }}]" class="form-select form-select-sm">
                    <option value="">Bebas</option>
                    @foreach($options[$c] ?? [] as $v)
                        <option value="{{ $v }}" @selected(($sim[$c] ?? null) === $v)>{{ $label($v) }}</option>
                    @endforeach
                </select>
            </div>
        @endforeach
        <div class="col-6 col-md">
            <label for="sim-budget" class="form-label small mb-1">Budget</label>
            <select id="sim-budget" name="sim[budget]" class="form-select form-select-sm">
                <option value="">Bebas</option>
                @foreach($budgetLabels as $k => $text)
                    <option value="{{ $k }}" @selected(($sim['budget'] ?? null) === $k)>{{ $text }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-12 col-md-auto"><button class="btn btn-sm btn-outline-plum w-100">Jalankan Simulasi</button></div>
    </form>

    @if($simulation)
        <div class="small mb-2"><strong>{{ $simulation->message }}</strong> · {{ $simulation->total }} bouquet cocok</div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr><th>#</th><th>Bouquet</th><th class="text-end">Harga</th><th class="text-center">Kecocokan</th><th>Alasan</th></tr></thead>
                <tbody>
                @foreach($simulation->items as $i => $item)
                    <tr>
                        <td>{{ $i + 1 }}</td>
                        <td>{{ $item['bouquet']->name }}</td>
                        <td class="text-end">{{ $rp($item['price']) }}</td>
                        <td class="text-center"><span class="badge-soft {{ $item['percent'] >= 80 ? 'success' : 'primary' }}">{{ $item['percent'] }}%</span></td>
                        <td class="small">{{ implode(' · ', $item['reasons']) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection

@push('scripts')
<script>
    // Slider & kotak angka saling sinkron; tombol simpan hanya aktif bila total tepat 100%.
    (() => {
        const form = document.querySelector('[data-weights-form]');
        if (!form) return;
        const ranges = form.querySelectorAll('.weight-range');
        const numbers = form.querySelectorAll('input[type=number]');
        const total = form.querySelector('[data-weight-total]');
        const status = form.querySelector('[data-weight-status]');
        const save = form.querySelector('[data-weight-save]');

        const update = () => {
            const sum = [...numbers].reduce((s, n) => s + (parseInt(n.value, 10) || 0), 0);
            total.textContent = sum;
            const ok = sum === 100;
            status.classList.toggle('text-danger', !ok);
            status.classList.toggle('text-success', ok);
            save.disabled = !ok;
        };
        ranges.forEach((r) => r.addEventListener('input', () => { document.getElementById(r.dataset.target).value = r.value; update(); }));
        numbers.forEach((n) => n.addEventListener('input', () => {
            const r = form.querySelector(`[data-target="${n.id}"]`);
            if (r) r.value = n.value;
            update();
        }));
        update();
    })();
</script>
@endpush
