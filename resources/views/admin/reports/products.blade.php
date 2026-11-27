@extends('layouts.admin')
@section('title', 'Best-selling Products')

@section('content')
@php $rp = fn ($v) => 'Rp ' . number_format($v, 0, ',', '.'); @endphp

@include('admin.reports._header', ['title' => 'Best-selling Products', 'subtitle' => 'Produk paling laku dan kontribusinya terhadap pendapatan.'])

@foreach(['Bouquet' => $bouquets, 'Bunga Per Tangkai' => $flowers] as $heading => $rows)
    <h2 class="section-title h6 mb-2 {{ $loop->first ? '' : 'mt-4' }}">{{ $heading }}</h2>
    <div class="card">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr><th style="width:3rem">#</th><th>Produk</th><th class="text-end">Terjual</th><th class="text-end">Pesanan</th><th class="text-end">Pendapatan</th><th class="text-end">Laba Kotor</th><th class="text-end">Margin</th><th style="width:22%">Kontribusi</th></tr>
                </thead>
                <tbody>
                @forelse($rows as $i => $r)
                    <tr>
                        <td class="text-muted">{{ $i + 1 }}</td>
                        <td class="fw-semibold">{{ $r['name'] }}</td>
                        <td class="text-end">{{ $r['qty'] }}{{ $heading === 'Bouquet' ? '' : ' tangkai' }}</td>
                        <td class="text-end">{{ $r['orders'] }}</td>
                        <td class="text-end">{{ $rp($r['revenue']) }}</td>
                        <td class="text-end {{ $r['profit'] === null ? 'text-muted' : ($r['profit'] < 0 ? 'text-danger' : 'text-success') }}">{{ $r['profit'] === null ? '—' : $rp($r['profit']) }}</td>
                        <td class="text-end">
                            @if($r['margin'] === null) <span class="text-muted" title="HPP belum diisi">—</span>
                            @else <span class="badge-soft {{ $r['margin'] >= \App\Models\Bouquet::MARGIN_HEALTHY ? 'success' : ($r['margin'] >= \App\Models\Bouquet::MARGIN_THIN ? 'warning' : 'danger') }}">{{ $r['margin'] }}%</span>
                            @endif
                        </td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <div class="bar flex-grow-1" role="img" aria-label="{{ $r['share'] }} persen"><span style="width: {{ $r['share'] }}%"></span></div>
                                <span class="small text-muted" style="min-width:3rem">{{ $r['share'] }}%</span>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-muted py-4">Belum ada penjualan lunas pada periode ini.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endforeach
<p class="small text-muted mt-3 mb-0">Laba kotor = pendapatan − harga modal (HPP) yang tercatat saat checkout. Tanda — berarti HPP belum diisi saat produk itu terjual. Bunga per tangkai belum memiliki HPP.</p>
@endsection
