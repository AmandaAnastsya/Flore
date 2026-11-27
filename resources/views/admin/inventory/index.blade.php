@extends('layouts.admin')
@section('title', 'Bunga & Supplies')

@section('content')
@php
    use App\Models\Flower;
    use App\Models\Supply;
    use App\Http\Controllers\Admin\InventoryController;
    $rp = fn ($v) => 'Rp ' . number_format($v, 0, ',', '.');
    $isStems = $tab === 'stems';
    $freshTone = ['fresh' => 'text-success', 'aging' => 'text-warning-emphasis', 'expired' => 'text-danger fw-semibold', 'unknown' => 'text-muted'];
@endphp

<div class="admin-head d-flex flex-wrap justify-content-between align-items-end gap-2">
    <div>
        <h1 class="h3 mb-1">Fresh Floral & Supply Inventory Hub</h1>
        <p>Pantau stok bunga segar, tingkat kesegaran, restock, dan perlengkapan wrapping.</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <form method="GET" class="d-flex gap-1">
            <input type="hidden" name="tab" value="{{ $tab }}">
            <input type="search" name="q" value="{{ $q }}" maxlength="100" class="form-control form-control-sm" placeholder="Search stem / supply item…" aria-label="Cari">
        </form>
        <a href="{{ $isStems ? route('admin.inventory.flowers.create') : route('admin.inventory.supplies.create', ['type' => $tab]) }}" class="btn btn-sm btn-plum">+ Tambah Stok Baru</a>
    </div>
</div>

@include('catalog._flash')
@if($errors->any()) <div class="alert alert-danger">{{ $errors->first() }}</div> @endif

<div class="row g-3 mb-4">
    <div class="col-6 col-xl-3"><div class="kpi">
        <div class="label">Total Stok Bunga</div>
        <div class="value">{{ number_format($kpi['stems'], 0, ',', '.') }} Batang</div>
        <div class="note text-muted">{{ $kpi['varieties'] }} varietas aktif</div>
    </div></div>
    <div class="col-6 col-xl-3"><div class="kpi {{ $kpi['critical'] ? 'alert-kpi' : '' }}">
        <div class="label">Stok Kritis (Restock)</div>
        <div class="value">{{ $kpi['critical'] }} Item</div>
        <div class="note text-muted">{{ $kpi['critical_names'] ?: 'Semua stok aman' }}</div>
    </div></div>
    <div class="col-6 col-xl-3"><div class="kpi">
        <div class="label">Kesegaran Rata-rata</div>
        <div class="value {{ ($kpi['freshness'] ?? 100) < 50 ? 'text-danger' : 'text-success' }}">{{ $kpi['freshness'] === null ? '—' : $kpi['freshness'] . '%' }}</div>
        <div class="note text-muted">{{ $kpi['expired'] ? $kpi['expired'] . ' varietas lewat umur segar' : 'dari tanggal bunga tiba' }}</div>
    </div></div>
    <div class="col-6 col-xl-3"><div class="kpi">
        <div class="label">Suplai Wrapping</div>
        <div class="value">{{ number_format($kpi['wrapping_meter'], 0, ',', '.') }} Meter</div>
        <div class="note text-muted">{{ $kpi['wrapping_items'] }} jenis pita & kertas</div>
    </div></div>
</div>

<nav class="nav report-tabs order-tabs flex-nowrap mb-3" aria-label="Kategori inventaris">
    @foreach(InventoryController::TABS as $key => $label)
        <a href="{{ route('admin.inventory.index', $key === 'stems' ? [] : ['tab' => $key]) }}" class="nav-link {{ $tab === $key ? 'active' : '' }}">{{ $label }}</a>
    @endforeach
</nav>

<div class="card">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>{{ $isStems ? 'Nama Varietas Bunga' : 'Nama Item' }}</th>
                    <th>{{ $isStems ? 'Jenis / Tone' : 'Varian' }}</th>
                    <th class="text-end">Stok</th>
                    @if($isStems) <th>Kesegaran</th> <th class="text-end">Harga / HPP</th> @else <th class="text-end">HPP / unit</th> @endif
                    <th>Status Stok</th>
                    <th class="text-end">Aksi</th>
                </tr>
            </thead>
            <tbody>
            @forelse($items as $item)
                @php
                    [$levelLabel, $levelTone] = Flower::LEVELS[$item->inventoryLevel()];
                    $kind = $isStems ? 'flower' : 'supply';
                    $unit = $isStems ? 'Stems' : $item->unit;
                @endphp
                <tr class="{{ $item->is_active ? '' : 'text-muted' }}">
                    <td>
                        <div class="fw-semibold">{{ $item->name }} @unless($item->is_active) <span class="badge bg-secondary">Nonaktif</span> @endunless</div>
                        <div class="small text-muted">{{ $isStems ? ($item->origin ?? '—') : ($item->variant ?? '') }}</div>
                    </td>
                    <td class="small">{{ $isStems ? ucfirst($item->species) . ($item->color ? ' / ' . ucfirst($item->color) : '') : ($item->variant ?? '—') }}</td>
                    <td class="text-end fw-semibold">{{ number_format($item->stock, 0, ',', '.') }} {{ $unit }}
                        <div class="small text-muted fw-normal">batas {{ $item->reorder_level }}</div>
                    </td>
                    @if($isStems)
                        <td class="small {{ $freshTone[$item->freshnessState()] }}">
                            @if($item->ageDays() === null) Tanggal tiba belum dicatat
                            @else Tiba {{ $item->ageDays() === 0 ? 'hari ini' : $item->ageDays() . ' hr lalu' }}
                                ({{ $item->remainingDays() > 0 ? 'sisa ' . $item->remainingDays() . ' hr' : 'lewat umur segar' }})
                            @endif
                        </td>
                        <td class="text-end small">{{ $rp($item->price_per_stem) }}
                            <div class="text-muted">HPP {{ $item->cost_per_stem !== null ? $rp($item->cost_per_stem) : '—' }}</div>
                        </td>
                    @else
                        <td class="text-end small">{{ $item->cost_per_unit !== null ? $rp($item->cost_per_unit) : '—' }}</td>
                    @endif
                    <td><span class="badge-soft {{ $levelTone }}">{{ $levelLabel }}</span></td>
                    <td class="text-end">
                        <div class="d-inline-flex gap-1 flex-wrap justify-content-end">
                            <a href="{{ $isStems ? route('admin.inventory.flowers.edit', $item) : route('admin.inventory.supplies.edit', $item) }}" class="btn btn-sm btn-outline-plum">Edit</a>
                            <details class="stock-action">
                                <summary class="btn btn-sm {{ $item->needsRestock() ? 'btn-plum' : 'btn-outline-secondary' }}">Reorder</summary>
                                <div class="stock-pop">
                                    <form method="POST" action="{{ route($isStems ? 'admin.inventory.flowers.restock' : 'admin.inventory.supplies.restock', $item) }}">
                                        @csrf
                                        <div class="fw-semibold small mb-2">Restock {{ $item->name }}</div>
                                        <label class="form-label small mb-1" for="qty-{{ $kind }}-{{ $item->id }}">Jumlah masuk ({{ $unit }})</label>
                                        <input id="qty-{{ $kind }}-{{ $item->id }}" type="number" name="quantity" min="1" required class="form-control form-control-sm mb-2">
                                        <label class="form-label small mb-1" for="cost-{{ $kind }}-{{ $item->id }}">HPP per unit baru <span class="text-muted">(opsional)</span></label>
                                        <input id="cost-{{ $kind }}-{{ $item->id }}" type="number" name="unit_cost" min="0" class="form-control form-control-sm mb-2" placeholder="{{ $item->unitCost() !== null ? (int) $item->unitCost() : '' }}">
                                        <input type="text" name="note" maxlength="200" class="form-control form-control-sm mb-2" placeholder="Catatan, mis. dari pasar Rawa Belong" aria-label="Catatan">
                                        <button class="btn btn-sm btn-plum w-100">Simpan Restock</button>
                                    </form>
                                    <hr class="my-2">
                                    <form method="POST" action="{{ route($isStems ? 'admin.inventory.flowers.adjust' : 'admin.inventory.supplies.adjust', $item) }}">
                                        @csrf
                                        <div class="fw-semibold small mb-2">Koreksi / buang</div>
                                        <div class="d-flex gap-1 mb-2">
                                            <input type="number" name="stock" min="0" value="{{ $item->stock }}" required class="form-control form-control-sm" aria-label="Stok baru">
                                            <select name="reason" class="form-select form-select-sm" aria-label="Alasan">
                                                <option value="adjustment">Stock opname</option>
                                                <option value="waste">{{ $isStems ? 'Layu / dibuang' : 'Rusak / dibuang' }}</option>
                                            </select>
                                        </div>
                                        <button class="btn btn-sm btn-outline-secondary w-100">Simpan Koreksi</button>
                                    </form>
                                </div>
                            </details>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="{{ $isStems ? 7 : 6 }}" class="text-center text-muted py-4">
                    {{ $q ? 'Tidak ada item yang cocok.' : 'Belum ada item di kategori ini.' }}
                </td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
<p class="small text-muted mt-2">Setiap restock, koreksi, dan bunga yang dibuang tercatat di <a href="{{ route('admin.reports.inventory') }}">Inventory Report</a>.</p>
@endsection
